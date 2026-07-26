<?php

use App\Enums\OperatingOrderStatus;
use App\Enums\OrganizationType;
use App\Models\OperatingOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Operating Orders')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    /**
     * The operating orders visible to the organization the user is currently
     * acting as — the operator sees every order it operates, an agent sees only
     * what it sent in. See OperatingOrder::scopeVisibleTo().
     */
    public function getOrdersProperty()
    {
        $organization = Auth::user()->currentOrganization;

        if (! $organization) {
            return OperatingOrder::whereRaw('1 = 0')->paginate(15);
        }

        return OperatingOrder::visibleTo($organization)
            ->with(['operator', 'agent', 'group'])
            ->withCount(['movements', 'hotelStays'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(15);
    }

    public function getCanCreateProperty(): bool
    {
        return Auth::user()->currentOrganization !== null;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Operating Orders')" :subtitle="__('The document that ties a client, a group, hotels and transport together.')">
        @if ($this->canCreate)
            <x-slot name="actions">
                <flux:button :href="route('operating-orders.create')" variant="primary" icon="plus" wire:navigate>
                    {{ __('New Operating Order') }}
                </flux:button>
            </x-slot>
        @endif
    </x-ui.page-header>

    <div class="flex items-center gap-3">
        <flux:select wire:model.live="status" class="max-w-56">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (\App\Enums\OperatingOrderStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-ui.card :padded="false">
    <flux:table :paginate="$this->orders">
        <flux:table.columns>
            <flux:table.column>{{ __('Reference') }}</flux:table.column>
            <flux:table.column>{{ __('Operator') }}</flux:table.column>
            <flux:table.column>{{ __('Agent') }}</flux:table.column>
            <flux:table.column minor>{{ __('Group') }}</flux:table.column>
            <flux:table.column>{{ __('Arrival') }}</flux:table.column>
            <flux:table.column minor>{{ __('Movements') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->orders as $order)
                <flux:table.row wire:key="order-{{ $order->id }}">
                    <flux:table.cell class="font-medium" :label="__('Reference')">
                        <flux:link :accent="false" :href="route('operating-orders.show', $order)" wire:navigate>{{ $order->reference_no }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell :label="__('Operator')">{{ $order->operator->name }}</flux:table.cell>
                    <flux:table.cell :label="__('Agent')">{{ $order->agent?->name ?? __('— (direct)') }}</flux:table.cell>
                    <flux:table.cell minor :label="__('Group')">{{ $order->group?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell :label="__('Arrival')">{{ $order->arrival_date?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                    <flux:table.cell minor :label="__('Movements')">{{ $order->movements_count }}</flux:table.cell>
                    <flux:table.cell :label="__('Status')">
                        <flux:badge size="sm" :color="match ($order->status) {
                            \App\Enums\OperatingOrderStatus::Approved => 'lime',
                            \App\Enums\OperatingOrderStatus::Completed => 'blue',
                            \App\Enums\OperatingOrderStatus::Cancelled => 'red',
                            default => 'zinc',
                        }">
                            {{ $order->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <x-ui.empty-state icon="clipboard-document-list" :title="__('No operating orders yet.')" />
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </x-ui.card>
</div>
