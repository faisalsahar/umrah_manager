<?php

use App\Enums\OperatingOrderStatus;
use App\Enums\OrganizationType;
use App\Models\Movement;
use App\Models\OperatingOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function getOrganizationProperty()
    {
        return Auth::user()->currentOrganization;
    }

    public function getOrdersProperty()
    {
        if (! $this->organization) {
            return collect();
        }

        return OperatingOrder::visibleTo($this->organization)->get();
    }

    public function getUpcomingMovementsProperty()
    {
        if (! $this->organization) {
            return collect();
        }

        return Movement::query()
            ->whereHas('operatingOrder', fn ($query) => $query->visibleTo($this->organization))
            ->where('movement_at', '>=', now())
            ->with(['operatingOrder', 'vehicle', 'movementType'])
            ->orderBy('movement_at')
            ->limit(8)
            ->get();
    }

    public function getRecentOrdersProperty()
    {
        if (! $this->organization) {
            return collect();
        }

        return OperatingOrder::visibleTo($this->organization)
            ->with(['operator', 'agent'])
            ->latest()
            ->limit(6)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    @if (! $this->organization)
        <x-ui.card class="text-center">
            <x-ui.empty-state icon="building-office-2" :title="__('You don\'t belong to an organization yet')">
                <x-slot name="action">
                    <flux:button :href="route('organizations.index')" variant="primary" wire:navigate>{{ __('Go to Organizations') }}</flux:button>
                </x-slot>
            </x-ui.empty-state>
            <flux:subheading class="mx-auto -mt-6 max-w-sm">{{ __('Create one, or ask an existing organization to add you by your onboarding code.') }}</flux:subheading>
        </x-ui.card>
    @else
        <x-ui.page-header :title="__('Dashboard')">
            <x-slot name="subtitle">
                {{ __('Acting as :organization', ['organization' => $this->organization->name]) }}
                &middot; <flux:link :href="route('organizations.index')" wire:navigate>{{ __('switch') }}</flux:link>
            </x-slot>
        </x-ui.page-header>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-ui.stat icon="clipboard-document-list" color="sky" :label="__('Total orders')" :value="$this->orders->count()" />
            <x-ui.stat icon="check-circle" color="lime" :label="__('Approved')" :value="$this->orders->where('status', OperatingOrderStatus::Approved)->count()" />
            <x-ui.stat icon="clock" color="amber" :label="__('Pending review')" :value="$this->orders->where('status', OperatingOrderStatus::PendingReview)->count()" />
            <x-ui.stat icon="truck" color="violet" :label="__('Upcoming movements')" :value="$this->upcomingMovements->count()" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-ui.card :padded="false">
                <flux:heading size="lg" class="p-6 pb-0">{{ __('Recent Operating Orders') }}</flux:heading>
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Reference') }}</flux:table.column>
                        <flux:table.column>{{ __('Agent') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->recentOrders as $order)
                            <flux:table.row wire:key="dash-order-{{ $order->id }}">
                                <flux:table.cell :label="__('Reference')">
                                    <flux:link :accent="false" :href="route('operating-orders.show', $order)" wire:navigate>{{ $order->reference_no }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell :label="__('Agent')">{{ $order->agent?->name ?? '—' }}</flux:table.cell>
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
                                <flux:table.cell colspan="3">
                                    <x-ui.empty-state icon="clipboard-document-list" :title="__('No operating orders yet.')" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </x-ui.card>

            <x-ui.card :padded="false">
                <flux:heading size="lg" class="p-6 pb-0">{{ __('Upcoming Movements') }}</flux:heading>
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->upcomingMovements as $movement)
                            <flux:table.row wire:key="dash-movement-{{ $movement->id }}">
                                <flux:table.cell :label="__('Order')">
                                    <flux:link :accent="false" :href="route('operating-orders.show', $movement->operatingOrder)" wire:navigate>{{ $movement->operatingOrder->reference_no }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell :label="__('When')">{{ $movement->movement_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                                <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $movement->movementType->name }}</flux:badge></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3">
                                    <x-ui.empty-state icon="truck" :title="__('No upcoming movements.')" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </x-ui.card>
        </div>
    @endif
</div>
