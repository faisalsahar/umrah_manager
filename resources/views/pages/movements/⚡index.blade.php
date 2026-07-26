<?php

use App\Enums\MovementStatus;
use App\Models\Movement;
use App\Models\MovementType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daily Movements')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $date = '';

    /**
     * Movements across every operating order this organization can see — the
     * operator sees all of its own; an agent sees only movements on orders it sent
     * in. Reuses OperatingOrder::scopeVisibleTo() through the relation.
     */
    public function getMovementsProperty()
    {
        $organization = Auth::user()->currentOrganization;

        if (! $organization) {
            return Movement::whereRaw('1 = 0')->paginate(20);
        }

        return Movement::query()
            ->whereHas('operatingOrder', fn ($query) => $query->visibleTo($organization))
            ->with(['operatingOrder.operator', 'operatingOrder.agent', 'movementType', 'vehicle.vehicleType'])
            ->when($this->type !== '', fn ($query) => $query->where('movement_type_id', $this->type))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->date !== '', fn ($query) => $query->whereDate('movement_at', $this->date))
            ->orderBy('movement_at')
            ->paginate(20);
    }

    /**
     * The type filter's options: whichever movement types actually appear among the
     * movements this organization can see (not a hardcoded operator scope — an agent
     * may see movements from more than one operator's catalog).
     */
    public function getMovementTypesProperty()
    {
        $organization = Auth::user()->currentOrganization;

        if (! $organization) {
            return collect();
        }

        $typeIds = Movement::query()
            ->whereHas('operatingOrder', fn ($query) => $query->visibleTo($organization))
            ->distinct()
            ->pluck('movement_type_id');

        return MovementType::whereIn('id', $typeIds)->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Daily Movements')" :subtitle="__('Every transport leg, across every operating order, in one place.')" />

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="type" class="max-w-48">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach ($this->movementTypes as $movementType)
                <flux:select.option value="{{ $movementType->id }}">{{ $movementType->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" class="max-w-48">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (\App\Enums\MovementStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input type="date" wire:model.live="date" class="max-w-48" />
    </div>

    <x-ui.card :padded="false">
    <flux:table :paginate="$this->movements">
        <flux:table.columns>
            <flux:table.column>{{ __('Order') }}</flux:table.column>
            <flux:table.column minor>{{ __('Agent') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('From') }}</flux:table.column>
            <flux:table.column>{{ __('To') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column minor>{{ __('Vehicle') }}</flux:table.column>
            <flux:table.column minor>{{ __('Pilgrims') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->movements as $movement)
                <flux:table.row wire:key="movement-{{ $movement->id }}">
                    <flux:table.cell :label="__('Order')">
                        <flux:link :accent="false" :href="route('operating-orders.show', $movement->operatingOrder)" wire:navigate>
                            {{ $movement->operatingOrder->reference_no }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell minor :label="__('Agent')">{{ $movement->operatingOrder->agent?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell :label="__('Type')">{{ $movement->movementType->name }}</flux:table.cell>
                    <flux:table.cell :label="__('From')">{{ $movement->from_location ?? '—' }}</flux:table.cell>
                    <flux:table.cell :label="__('To')">{{ $movement->to_location ?? '—' }}</flux:table.cell>
                    <flux:table.cell :label="__('When')">{{ $movement->movement_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                    <flux:table.cell minor :label="__('Vehicle')">{{ $movement->vehicle?->plate_no ?? '—' }}</flux:table.cell>
                    <flux:table.cell minor :label="__('Pilgrims')">{{ $movement->pilgrim_count }}</flux:table.cell>
                    <flux:table.cell :label="__('Status')">
                        <flux:badge size="sm" :color="match ($movement->status) {
                            \App\Enums\MovementStatus::Completed => 'lime',
                            \App\Enums\MovementStatus::Flagged => 'red',
                            \App\Enums\MovementStatus::Confirmed, \App\Enums\MovementStatus::InProgress => 'blue',
                            default => 'zinc',
                        }">
                            {{ $movement->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="9">
                        <x-ui.empty-state icon="truck" :title="__('No movements match these filters.')" />
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </x-ui.card>
</div>
