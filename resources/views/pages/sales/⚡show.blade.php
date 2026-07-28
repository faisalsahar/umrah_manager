<?php

use App\Enums\AgentSalePaymentDirection;
use App\Enums\AgentSaleStatus;
use App\Models\AgentSale;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sale')] class extends Component {
    #[Locked]
    public int $saleId;

    public string $customerPaymentAmount = '';

    public string $customerPaymentDate = '';

    public string $customerPaymentNote = '';

    public string $supplierPaymentAmount = '';

    public string $supplierPaymentDate = '';

    public string $supplierPaymentNote = '';

    public function mount(AgentSale $sale): void
    {
        abort_unless($sale->agent_organization_id === Auth::user()->current_organization_id, 403);

        $this->saleId = $sale->id;
        $this->customerPaymentDate = now()->format('Y-m-d');
        $this->supplierPaymentDate = now()->format('Y-m-d');
    }

    public function getSaleProperty()
    {
        return AgentSale::with(['payments', 'recordedBy'])->findOrFail($this->saleId);
    }

    public function getCanManageProperty(): bool
    {
        $current = Auth::user()->currentOrganization;

        return $current && Auth::user()->roleIn($current)?->managesOrganization();
    }

    public function recordCustomerPayment(): void
    {
        abort_unless($this->sale->status === AgentSaleStatus::Issued, 403,
            __('A refunded or voided sale can no longer take payments.'));

        $validated = $this->validate([
            'customerPaymentAmount' => ['required', 'numeric', 'min:0.01'],
            'customerPaymentDate' => ['required', 'date'],
            'customerPaymentNote' => ['nullable', 'string', 'max:255'],
        ]);

        $this->sale->payments()->create([
            'direction' => AgentSalePaymentDirection::FromCustomer,
            'amount_cents' => (int) round($validated['customerPaymentAmount'] * 100),
            'paid_on' => $validated['customerPaymentDate'],
            'note' => $validated['customerPaymentNote'] ?: null,
        ]);

        $this->reset(['customerPaymentAmount', 'customerPaymentNote']);
        $this->customerPaymentDate = now()->format('Y-m-d');
        \Flux\Flux::toast(text: __('Customer payment recorded.'), variant: 'success');
    }

    public function recordSupplierPayment(): void
    {
        abort_unless($this->canManage, 403);
        abort_unless($this->sale->status === AgentSaleStatus::Issued, 403,
            __('A refunded or voided sale can no longer take payments.'));

        $validated = $this->validate([
            'supplierPaymentAmount' => ['required', 'numeric', 'min:0.01'],
            'supplierPaymentDate' => ['required', 'date'],
            'supplierPaymentNote' => ['nullable', 'string', 'max:255'],
        ]);

        $this->sale->payments()->create([
            'direction' => AgentSalePaymentDirection::ToSupplier,
            'amount_cents' => (int) round($validated['supplierPaymentAmount'] * 100),
            'paid_on' => $validated['supplierPaymentDate'],
            'note' => $validated['supplierPaymentNote'] ?: null,
        ]);

        $this->reset(['supplierPaymentAmount', 'supplierPaymentNote']);
        $this->supplierPaymentDate = now()->format('Y-m-d');
        \Flux\Flux::toast(text: __('Supplier payment recorded.'), variant: 'success');
    }

    /**
     * Only ever reachable from an Issued sale (see the buttons above), and only to
     * one of these two terminal states — not a general-purpose status setter, so an
     * invalid or unexpected value (a crafted request calling this directly) fails
     * validation instead of a raw enum ValueError.
     */
    public function markAs(string $status): void
    {
        abort_unless($this->canManage, 403);
        abort_unless($this->sale->status === AgentSaleStatus::Issued, 403);

        validator(['status' => $status], [
            'status' => ['required', \Illuminate\Validation\Rule::in(['refunded', 'voided'])],
        ])->validate();

        $this->sale->update(['status' => AgentSaleStatus::from($status)]);
        \Flux\Flux::toast(text: __('Sale updated.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="$this->sale->reference_no">
        <x-slot name="subtitle">
            {{ $this->sale->customer_name }} &middot; {{ $this->sale->sale_type->label() }}
        </x-slot>
        <x-slot name="actions">
            <flux:badge size="lg" :color="match ($this->sale->status) {
                \App\Enums\AgentSaleStatus::Issued => 'lime',
                \App\Enums\AgentSaleStatus::Refunded => 'amber',
                \App\Enums\AgentSaleStatus::Voided => 'zinc',
            }">
                {{ $this->sale->status->label() }}
            </flux:badge>
            @if ($this->canManage && $this->sale->status === \App\Enums\AgentSaleStatus::Issued)
                <flux:button size="sm" variant="ghost" wire:click="markAs('refunded')">{{ __('Mark refunded') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="markAs('voided')">{{ __('Void') }}</flux:button>
            @endif
        </x-slot>
    </x-ui.page-header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-ui.stat icon="banknotes" color="sky" :label="__('Sale price')" :value="number_format($this->sale->sale_price_cents / 100, 2).' '.$this->sale->currency" />
        <x-ui.stat icon="truck" color="zinc" :label="__('Cost')" :value="number_format($this->sale->purchase_price_cents / 100, 2)" />
        <x-ui.stat icon="chart-bar" color="lime" :label="__('Margin')" :value="number_format($this->sale->marginCents() / 100, 2)" />
        <x-ui.stat icon="clock" color="amber" :label="__('Outstanding from customer')" :value="number_format(max(0, $this->sale->customerOutstandingCents()) / 100, 2)" />
    </div>

    @if ($this->sale->description || $this->sale->supplier_reference)
        <x-ui.card>
            @if ($this->sale->description)
                <div class="mb-2"><span class="font-medium">{{ __('Description') }}:</span> {{ $this->sale->description }}</div>
            @endif
            @if ($this->sale->supplier_reference)
                <div><span class="font-medium">{{ __('Supplier reference') }}:</span> {{ $this->sale->supplier_reference }}</div>
            @endif
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card>
            <flux:heading size="lg" class="mb-1">{{ __('From customer') }}</flux:heading>
            <flux:subheading class="mb-4">
                {{ __('Received :received of :total — :outstanding outstanding.', [
                    'received' => number_format($this->sale->amountReceivedCents() / 100, 2),
                    'total' => number_format($this->sale->sale_price_cents / 100, 2),
                    'outstanding' => number_format(max(0, $this->sale->customerOutstandingCents()) / 100, 2),
                ]) }}
            </flux:subheading>

            @if ($this->sale->status === \App\Enums\AgentSaleStatus::Issued)
                <form wire:submit="recordCustomerPayment" class="mb-4 flex flex-wrap items-end gap-3">
                    <flux:input type="number" step="0.01" min="0" wire:model="customerPaymentAmount" :label="__('Amount')" class="max-w-32" />
                    <flux:input type="date" wire:model="customerPaymentDate" :label="__('Date')" class="max-w-40" />
                    <flux:input wire:model="customerPaymentNote" :label="__('Note')" class="max-w-48" />
                    <flux:button type="submit" size="sm">{{ __('Record') }}</flux:button>
                </form>
            @else
                <p class="mb-4 text-sm text-zinc-500">{{ __('A refunded or voided sale can no longer take payments.') }}</p>
            @endif

            <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                @forelse ($this->sale->payments->where('direction', \App\Enums\AgentSalePaymentDirection::FromCustomer) as $payment)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span>{{ $payment->paid_on->format('Y-m-d') }} @if ($payment->note) &middot; {{ $payment->note }} @endif</span>
                        <span class="font-medium">{{ number_format($payment->amount_cents / 100, 2) }}</span>
                    </div>
                @empty
                    <p class="py-2 text-sm text-zinc-500">{{ __('No payments recorded yet.') }}</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card>
            <flux:heading size="lg" class="mb-1">{{ __('To supplier') }}</flux:heading>
            <flux:subheading class="mb-4">
                {{ __('Paid :paid of :total — :outstanding outstanding.', [
                    'paid' => number_format($this->sale->amountPaidToSupplierCents() / 100, 2),
                    'total' => number_format($this->sale->purchase_price_cents / 100, 2),
                    'outstanding' => number_format(max(0, $this->sale->supplierOutstandingCents()) / 100, 2),
                ]) }}
            </flux:subheading>

            @if ($this->canManage && $this->sale->status === \App\Enums\AgentSaleStatus::Issued)
                <form wire:submit="recordSupplierPayment" class="mb-4 flex flex-wrap items-end gap-3">
                    <flux:input type="number" step="0.01" min="0" wire:model="supplierPaymentAmount" :label="__('Amount')" class="max-w-32" />
                    <flux:input type="date" wire:model="supplierPaymentDate" :label="__('Date')" class="max-w-40" />
                    <flux:input wire:model="supplierPaymentNote" :label="__('Note')" class="max-w-48" />
                    <flux:button type="submit" size="sm">{{ __('Record') }}</flux:button>
                </form>
            @elseif ($this->sale->status !== \App\Enums\AgentSaleStatus::Issued)
                <p class="mb-4 text-sm text-zinc-500">{{ __('A refunded or voided sale can no longer take payments.') }}</p>
            @endif

            <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                @forelse ($this->sale->payments->where('direction', \App\Enums\AgentSalePaymentDirection::ToSupplier) as $payment)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span>{{ $payment->paid_on->format('Y-m-d') }} @if ($payment->note) &middot; {{ $payment->note }} @endif</span>
                        <span class="font-medium">{{ number_format($payment->amount_cents / 100, 2) }}</span>
                    </div>
                @empty
                    <p class="py-2 text-sm text-zinc-500">{{ __('No payments recorded yet.') }}</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</div>
