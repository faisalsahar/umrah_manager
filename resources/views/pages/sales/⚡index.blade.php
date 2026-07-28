<?php

use App\Enums\AgentSaleStatus;
use App\Enums\AgentSaleType;
use App\Enums\OrganizationType;
use App\Models\AgentSale;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Sales')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    public string $newSaleType = '';

    public string $newCustomerName = '';

    public string $newDescription = '';

    public string $newSupplierReference = '';

    public string $newSaleDate = '';

    public string $newPurchasePrice = '';

    public string $newSalePrice = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Agent, 403,
            __('Only an agent records its own sales here. Switch to your agent organization first.'));

        $this->newSaleDate = now()->format('Y-m-d');
    }

    /**
     * Every sale this agent has recorded — never scoped to any other organization,
     * since this is entirely the agent's own business.
     */
    public function getSalesProperty()
    {
        $agentId = Auth::user()->current_organization_id;

        return AgentSale::where('agent_organization_id', $agentId)
            ->when($this->type !== '', fn ($query) => $query->where('sale_type', $this->type))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->with('payments')
            ->latest('sale_date')
            ->paginate(15);
    }

    /**
     * Summary across every non-voided sale this agent has on file — a voided sale
     * never happened financially, so it's excluded from every total below.
     */
    public function getTotalsProperty(): array
    {
        $agentId = Auth::user()->current_organization_id;

        $sales = AgentSale::where('agent_organization_id', $agentId)
            ->where('status', '!=', AgentSaleStatus::Voided)
            ->with('payments')
            ->get();

        return [
            'sale_cents' => $sales->sum('sale_price_cents'),
            'cost_cents' => $sales->sum('purchase_price_cents'),
            'margin_cents' => $sales->sum(fn (AgentSale $sale): int => $sale->marginCents()),
            'outstanding_cents' => $sales->sum(fn (AgentSale $sale): int => max(0, $sale->customerOutstandingCents())),
        ];
    }

    public function getCanManageProperty(): bool
    {
        $current = Auth::user()->currentOrganization;

        return $current && Auth::user()->roleIn($current)?->managesOrganization();
    }

    public function createSale(): void
    {
        $validated = $this->validate([
            'newSaleType' => ['required', new \Illuminate\Validation\Rules\Enum(AgentSaleType::class)],
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newDescription' => ['nullable', 'string', 'max:255'],
            'newSupplierReference' => ['nullable', 'string', 'max:255'],
            'newSaleDate' => ['required', 'date'],
            'newPurchasePrice' => ['required', 'numeric', 'min:0'],
            'newSalePrice' => ['required', 'numeric', 'min:0'],
        ]);

        AgentSale::create([
            'agent_organization_id' => Auth::user()->current_organization_id,
            'recorded_by_user_id' => Auth::id(),
            'sale_type' => $validated['newSaleType'],
            'customer_name' => $validated['newCustomerName'],
            'description' => $validated['newDescription'] ?: null,
            'supplier_reference' => $validated['newSupplierReference'] ?: null,
            'sale_date' => $validated['newSaleDate'],
            'purchase_price_cents' => (int) round($validated['newPurchasePrice'] * 100),
            'sale_price_cents' => (int) round($validated['newSalePrice'] * 100),
        ]);

        $this->reset([
            'newSaleType', 'newCustomerName', 'newDescription', 'newSupplierReference',
            'newPurchasePrice', 'newSalePrice',
        ]);
        $this->newSaleDate = now()->format('Y-m-d');

        \Flux\Flux::toast(text: __('Sale recorded.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Sales')" :subtitle="__('Tickets and everything else you sell directly to your own customers.')" />

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-ui.stat icon="banknotes" color="sky" :label="__('Total sales')" :value="number_format($this->totals['sale_cents'] / 100, 2)" />
        <x-ui.stat icon="truck" color="zinc" :label="__('Total cost')" :value="number_format($this->totals['cost_cents'] / 100, 2)" />
        <x-ui.stat icon="chart-bar" color="lime" :label="__('Margin')" :value="number_format($this->totals['margin_cents'] / 100, 2)" />
        <x-ui.stat icon="clock" color="amber" :label="__('Outstanding from customers')" :value="number_format($this->totals['outstanding_cents'] / 100, 2)" />
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="type" class="max-w-48">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (AgentSaleType::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" class="max-w-48">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (AgentSaleStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-ui.card :padded="false">
        <flux:table :paginate="$this->sales">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column minor>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Sale') }}</flux:table.column>
                <flux:table.column minor>{{ __('Margin') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->sales as $sale)
                    <flux:table.row wire:key="sale-{{ $sale->id }}">
                        <flux:table.cell class="font-medium" :label="__('Reference')">
                            <flux:link :accent="false" :href="route('sales.show', $sale)" wire:navigate>{{ $sale->reference_no }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $sale->sale_type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell :label="__('Customer')">{{ $sale->customer_name }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Date')">{{ $sale->sale_date->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell :label="__('Sale')">{{ number_format($sale->sale_price_cents / 100, 2) }} {{ $sale->currency }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Margin')">{{ number_format($sale->marginCents() / 100, 2) }}</flux:table.cell>
                        <flux:table.cell :label="__('Status')">
                            <flux:badge size="sm" :color="match ($sale->status) {
                                \App\Enums\AgentSaleStatus::Issued => 'lime',
                                \App\Enums\AgentSaleStatus::Refunded => 'amber',
                                \App\Enums\AgentSaleStatus::Voided => 'zinc',
                            }">
                                {{ $sale->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <x-ui.empty-state icon="banknotes" :title="__('No sales recorded yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg" class="mb-4">{{ __('Record a sale') }}</flux:heading>
        <form wire:submit="createSale" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <flux:select wire:model="newSaleType" :label="__('Type')">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach (AgentSaleType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="newCustomerName" :label="__('Customer name')" />
            <flux:input wire:model="newDescription" :label="__('Description')" placeholder="Riyadh — Jeddah, return" />
            <flux:input wire:model="newSupplierReference" :label="__('Supplier reference')" placeholder="PNR / ticket no." />
            <flux:input type="date" wire:model="newSaleDate" :label="__('Sale date')" />
            <flux:input type="number" step="0.01" min="0" wire:model="newPurchasePrice" :label="__('Cost (paid to supplier)')" />
            <flux:input type="number" step="0.01" min="0" wire:model="newSalePrice" :label="__('Price (charged to customer)')" />
            <div class="flex items-end">
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </x-ui.card>
</div>
