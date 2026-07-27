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

    /**
     * The current user's own onboarding code — generated on first login (see
     * AppServiceProvider::issueOnboardingCodeOnLogin()), guaranteed non-blank
     * by the time they can reach this page.
     */
    public function getOnboardingCodeProperty(): string
    {
        return Auth::user()->ensureOnboardingCode();
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
        <x-ui.card class="mx-auto max-w-lg text-center">
            <x-ui.empty-state icon="building-office-2" :title="__('You don\'t belong to an organization yet')" />

            <flux:subheading class="mx-auto -mt-3 max-w-sm">
                {{ __('Share the code below with an Umrah company, agent, or supplier you work with — they can add you to their team with it, no email address needed.') }}
            </flux:subheading>

            <div
                class="mx-auto mt-5 flex max-w-xs items-center justify-center gap-2"
                x-data="{
                    copied: false,
                    async copy() {
                        try {
                            await navigator.clipboard.writeText('{{ $this->onboardingCode }}');
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1500);
                        } catch (e) {
                            console.warn('Could not copy to clipboard');
                        }
                    },
                }"
            >
                <div class="flex-1 rounded-[5px] border border-zinc-200/80 bg-zinc-50 px-4 py-3 font-mono text-2xl font-semibold tracking-[0.3em] text-zinc-800 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    {{ $this->onboardingCode }}
                </div>
                <flux:button @click="copy()" icon="document-duplicate">
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </flux:button>
            </div>

            <flux:subheading class="mx-auto mt-5 max-w-sm">
                {{ __('Prefer to run your own company, agency, or supplier instead? You can start one yourself.') }}
            </flux:subheading>
            <flux:button :href="route('organizations.index')" variant="primary" class="mt-3" wire:navigate>
                {{ __('Go to Organizations') }}
            </flux:button>
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
