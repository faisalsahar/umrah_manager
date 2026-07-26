<?php

use App\Enums\MovementSource;
use App\Enums\MovementStatus;
use App\Enums\OperatingOrderStatus;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Operating Order')] class extends Component {
    #[Locked]
    public int $orderId;

    public bool $showAddMovement = false;

    public string $movementTypeId = '';

    public string $source = '';

    public string $fromLocation = '';

    public string $toLocation = '';

    public string $movementAt = '';

    public string $vehicleId = '';

    public string $supplierReferenceNo = '';

    public int $pilgrimCount = 0;

    public function mount(OperatingOrder $operatingOrder): void
    {
        $this->authorize('view', $operatingOrder);

        $this->orderId = $operatingOrder->id;
    }

    public function getOrderProperty()
    {
        return OperatingOrder::with([
            'operator', 'agent', 'group.pilgrims', 'enteredBy',
            'arrivalCity', 'arrivalPort', 'departureCity', 'departurePort',
            'movements.movementType', 'movements.vehicle.vehicleType', 'movements.transportSupplier',
            'hotelStays.hotel', 'hotelStays.roomType', 'hotelStays.pilgrim',
            'orderLines.service',
            'ledgerEntries.counterparty',
        ])->findOrFail($this->orderId);
    }

    public function getCanManageProperty(): bool
    {
        return Auth::user()->can('update', $this->order);
    }

    public function getVehiclesProperty()
    {
        return Vehicle::where('operator_id', $this->order->operator_id)->with('vehicleType')->get();
    }

    public function getMovementTypesProperty()
    {
        return MovementType::where('operator_id', $this->order->operator_id)->where('is_active', true)->get();
    }

    public function approve(): void
    {
        $order = $this->order;
        $this->authorize('update', $order);

        $order->update(['status' => OperatingOrderStatus::Approved, 'approved_at' => now()]);
        \Flux\Flux::toast(text: __('Order approved.'), variant: 'success');
    }

    public function cancel(): void
    {
        $order = $this->order;
        $this->authorize('update', $order);

        $order->update(['status' => OperatingOrderStatus::Cancelled]);
        \Flux\Flux::toast(text: __('Order cancelled.'), variant: 'warning');
    }

    public function addMovement(): void
    {
        $order = $this->order;
        $this->authorize('update', $order);

        $validated = $this->validate([
            // Scoped to this order's operator — otherwise a tampered request could
            // attach another operator's movement-type catalog entry or vehicle here.
            'movementTypeId' => ['required', 'integer', Rule::exists('movement_types', 'id')->where('operator_id', $order->operator_id)],
            'source' => ['required', new \Illuminate\Validation\Rules\Enum(MovementSource::class)],
            'fromLocation' => ['nullable', 'string', 'max:255'],
            'toLocation' => ['nullable', 'string', 'max:255'],
            'movementAt' => ['nullable', 'date'],
            'vehicleId' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('operator_id', $order->operator_id)],
            'supplierReferenceNo' => ['nullable', 'string', 'max:255'],
            'pilgrimCount' => ['required', 'integer', 'min:0'],
        ]);

        $order->movements()->create([
            'movement_type_id' => $validated['movementTypeId'],
            'source' => $validated['source'],
            'from_location' => $validated['fromLocation'] ?: null,
            'to_location' => $validated['toLocation'] ?: null,
            'movement_at' => $validated['movementAt'] ?: null,
            'vehicle_id' => $validated['vehicleId'] ?: null,
            'supplier_reference_no' => $validated['supplierReferenceNo'] ?: null,
            'pilgrim_count' => $validated['pilgrimCount'],
        ]);

        $this->reset(['movementTypeId', 'source', 'fromLocation', 'toLocation', 'movementAt', 'vehicleId', 'supplierReferenceNo', 'pilgrimCount']);
        $this->showAddMovement = false;
        \Flux\Flux::toast(text: __('Movement added.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="$this->order->reference_no">
        <x-slot name="subtitle">
            {{ $this->order->operator->name }}
            @if ($this->order->agent)
                &middot; {{ __('Agent') }}: {{ $this->order->agent->name }}
            @endif
            @if ($this->order->group)
                &middot; {{ __('Group') }}: {{ $this->order->group->name }}
            @endif
        </x-slot>
        <x-slot name="actions">
            <flux:badge size="lg" :color="match ($this->order->status) {
                \App\Enums\OperatingOrderStatus::Approved => 'lime',
                \App\Enums\OperatingOrderStatus::Completed => 'blue',
                \App\Enums\OperatingOrderStatus::Cancelled => 'red',
                default => 'zinc',
            }">
                {{ $this->order->status->label() }}
            </flux:badge>

            @if ($this->canManage && in_array($this->order->status, [\App\Enums\OperatingOrderStatus::Draft, \App\Enums\OperatingOrderStatus::PendingReview]))
                <flux:button size="sm" variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancel">{{ __('Cancel Order') }}</flux:button>
            @endif
        </x-slot>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <x-ui.stat icon="calendar-days" color="sky" :label="__('Arrival')" :value="$this->order->arrival_date?->format('Y-m-d') ?? '—'" />
        <x-ui.stat icon="users" color="violet" :label="__('Pilgrims')" :value="$this->order->totalPilgrimCount() ?? $this->order->group?->pilgrims->count() ?? '—'" />
        <x-ui.stat icon="banknotes" color="lime" :label="__('Sale total')" :value="number_format($this->order->totalSaleCents() / 100, 2).' '.($this->order->orderLines->first()?->currency ?? 'SAR')" />
        <x-ui.stat icon="chart-bar" color="amber" :label="__('Margin')" :value="number_format($this->order->marginCents() / 100, 2)" />
    </div>

    {{-- Logistics --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-ui.card>
            <flux:heading size="lg" class="mb-4">{{ __('Arrival') }}</flux:heading>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-zinc-500">{{ __('Trip mode') }}</dt>
                <dd>{{ $this->order->arrival_trip_mode?->label() ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('City') }}</dt>
                <dd>{{ $this->order->arrivalCity?->name ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Port') }}</dt>
                <dd>{{ $this->order->arrivalPort?->name ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Carrier') }}</dt>
                <dd>{{ $this->order->arrival_carrier ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Flight / trip no.') }}</dt>
                <dd>{{ $this->order->arrival_flight_no ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Time') }}</dt>
                <dd>{{ $this->order->arrival_time ?? '—' }}</dd>
            </dl>
        </x-ui.card>
        <x-ui.card>
            <flux:heading size="lg" class="mb-4">{{ __('Departure') }}</flux:heading>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-zinc-500">{{ __('Trip mode') }}</dt>
                <dd>{{ $this->order->departure_trip_mode?->label() ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('City') }}</dt>
                <dd>{{ $this->order->departureCity?->name ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Port') }}</dt>
                <dd>{{ $this->order->departurePort?->name ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Carrier') }}</dt>
                <dd>{{ $this->order->departure_carrier ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Flight / trip no.') }}</dt>
                <dd>{{ $this->order->departure_flight_no ?? '—' }}</dd>
                <dt class="text-zinc-500">{{ __('Time') }}</dt>
                <dd>{{ $this->order->departure_time ?? '—' }}</dd>
            </dl>
        </x-ui.card>
    </div>

    {{-- Movements --}}
    <x-ui.card :padded="false">
        <div class="flex items-center justify-between p-6 pb-0">
            <flux:heading size="lg">{{ __('Movements') }}</flux:heading>
            @if ($this->canManage)
                <flux:button size="sm" icon="plus" wire:click="$toggle('showAddMovement')">
                    {{ $showAddMovement ? __('Cancel') : __('Add movement') }}
                </flux:button>
            @endif
        </div>

        @if ($showAddMovement)
            <form wire:submit="addMovement" class="m-6 grid grid-cols-2 gap-4 rounded-xl border border-zinc-200 p-4 sm:grid-cols-3 dark:border-white/10">
                <flux:select wire:model="movementTypeId" :label="__('Type')">
                    <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                    @foreach ($this->movementTypes as $movementType)
                        <flux:select.option value="{{ $movementType->id }}">{{ $movementType->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="source" :label="__('Source')">
                    <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                    @foreach (\App\Enums\MovementSource::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="fromLocation" :label="__('From')" />
                <flux:input wire:model="toLocation" :label="__('To')" />
                <flux:input type="datetime-local" wire:model="movementAt" :label="__('Date / time')" />
                <flux:select wire:model="vehicleId" :label="__('Vehicle')">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->vehicles as $vehicle)
                        <flux:select.option value="{{ $vehicle->id }}">{{ $vehicle->vehicleType->name }} — {{ $vehicle->plate_no }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="supplierReferenceNo" :label="__('Supplier reference no.')" />
                <flux:input type="number" min="0" wire:model="pilgrimCount" :label="__('Pilgrim count')" />
                <div class="col-span-full flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        @endif

        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('From') }}</flux:table.column>
                <flux:table.column>{{ __('To') }}</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column minor>{{ __('Vehicle') }}</flux:table.column>
                <flux:table.column minor>{{ __('Source') }}</flux:table.column>
                <flux:table.column minor>{{ __('Ref. no.') }}</flux:table.column>
                <flux:table.column minor>{{ __('Pilgrims') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->order->movements as $movement)
                    <flux:table.row wire:key="movement-{{ $movement->id }}">
                        <flux:table.cell :label="__('Type')">{{ $movement->movementType->name }}</flux:table.cell>
                        <flux:table.cell :label="__('From')">{{ $movement->from_location ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('To')">{{ $movement->to_location ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('When')">{{ $movement->movement_at?->format('Y-m-d H:i') ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Vehicle')">{{ $movement->vehicle?->plate_no ?? ($movement->transportSupplier?->name ?? '—') }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Source')"><flux:badge size="sm">{{ $movement->source->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell minor :label="__('Ref. no.')">{{ $movement->supplier_reference_no ?? '—' }}</flux:table.cell>
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
                            <x-ui.empty-state icon="truck" :title="__('No movements yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    {{-- Hotel stays --}}
    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Hotel Stays') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Pilgrim') }}</flux:table.column>
                <flux:table.column>{{ __('Hotel') }}</flux:table.column>
                <flux:table.column>{{ __('Room type') }}</flux:table.column>
                <flux:table.column>{{ __('Check in') }}</flux:table.column>
                <flux:table.column>{{ __('Check out') }}</flux:table.column>
                <flux:table.column minor>{{ __('Source') }}</flux:table.column>
                <flux:table.column minor>{{ __('Ref. no.') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->order->hotelStays as $stay)
                    <flux:table.row wire:key="stay-{{ $stay->id }}">
                        <flux:table.cell :label="__('Pilgrim')">{{ $stay->pilgrim->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Hotel')">{{ $stay->hotel->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Room type')">{{ $stay->roomType->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Check in')">{{ $stay->check_in->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell :label="__('Check out')">{{ $stay->check_out->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Source')"><flux:badge size="sm">{{ $stay->source->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell minor :label="__('Ref. no.')">{{ $stay->supplier_reference_no ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <x-ui.empty-state icon="building-office-2" :title="__('No hotel stays recorded on this order.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <flux:text class="p-6 pt-3 text-xs text-zinc-500">
            {{ __('Stays the operator never sold (the agent brought its own hotel booking) still appear on the pilgrim\'s record, but not here — see docs/PLAN.md §3.') }}
        </flux:text>
    </x-ui.card>

    {{-- Order lines --}}
    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Order Lines') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column minor>{{ __('Qty') }}</flux:table.column>
                <flux:table.column minor>{{ __('Purchase') }}</flux:table.column>
                <flux:table.column>{{ __('Sale') }}</flux:table.column>
                <flux:table.column>{{ __('Margin') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->order->orderLines as $line)
                    <flux:table.row wire:key="line-{{ $line->id }}">
                        <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $line->line_type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell :label="__('Description')">{{ $line->description ?? $line->service?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Qty')">{{ $line->quantity }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Purchase')">{{ number_format($line->purchase_price_cents / 100, 2) }}</flux:table.cell>
                        <flux:table.cell :label="__('Sale')">{{ number_format($line->sale_price_cents / 100, 2) }}</flux:table.cell>
                        <flux:table.cell :label="__('Margin')">{{ number_format($line->marginCents() / 100, 2) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="receipt-percent" :title="__('No lines sold on this order yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    {{-- Ledger --}}
    @if ($this->order->agent)
        <x-ui.card :padded="false">
            <div class="flex items-center justify-between p-6 pb-0">
                <flux:heading size="lg">{{ __('Ledger with :agent', ['agent' => $this->order->agent->name]) }}</flux:heading>
                <flux:text>
                    {{ __('Balance owed to operator:') }}
                    <span class="font-semibold">{{ number_format($this->order->operator->balanceWith($this->order->agent) / 100, 2) }} SAR</span>
                </flux:text>
            </div>
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column minor>{{ __('Description') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->order->ledgerEntries as $entry)
                        <flux:table.row wire:key="ledger-{{ $entry->id }}">
                            <flux:table.cell :label="__('Date')">{{ $entry->occurred_on->format('Y-m-d') }}</flux:table.cell>
                            <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $entry->entry_type->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell minor :label="__('Description')">{{ $entry->description ?? '—' }}</flux:table.cell>
                            <flux:table.cell :label="__('Amount')">{{ number_format($entry->amount_cents / 100, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
                                <x-ui.empty-state icon="banknotes" :title="__('No financial entries on this order yet.')" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-ui.card>
    @endif
</div>
