<?php

use App\Enums\HotelContractType;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hotel')] class extends Component {
    #[Locked]
    public int $hotelId;

    public string $roomTypeName = '';

    public string $roomTypeCapacity = '';

    public string $contractRoomTypeId = '';

    public string $contractType = '';

    public string $contractStartsOn = '';

    public string $contractEndsOn = '';

    public string $contractReleaseDays = '14';

    public string $ratePurchase = '';

    public string $rateSale = '';

    public function mount(Hotel $hotel): void
    {
        abort_unless(Auth::user()->belongsToOrganization($hotel->operator), 403);

        $this->hotelId = $hotel->id;
    }

    public function getHotelProperty()
    {
        return Hotel::with(['roomTypes', 'contracts.rates.roomType'])
            ->withCount('roomTypes')
            ->findOrFail($this->hotelId);
    }

    public function getCanManageProperty(): bool
    {
        $current = Auth::user()->currentOrganization;

        return $current && Auth::user()->roleIn($current)?->managesOrganization();
    }

    public function confirmHotel(): void
    {
        abort_unless($this->canManage, 403);
        $this->hotel->confirm(Auth::user());
        \Flux\Flux::toast(text: __('Hotel confirmed.'), variant: 'success');
    }

    public function addRoomType(): void
    {
        $hotel = $this->hotel;

        $validated = $this->validate([
            'roomTypeName' => ['required', 'string', 'max:255'],
            'roomTypeCapacity' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $hotel->roomTypes()->create([
            'name' => $validated['roomTypeName'],
            'capacity' => $validated['roomTypeCapacity'],
        ]);

        $this->reset(['roomTypeName', 'roomTypeCapacity']);
        \Flux\Flux::toast(text: __('Room type added.'), variant: 'success');
    }

    public function addContract(): void
    {
        $hotel = $this->hotel;

        $validated = $this->validate([
            // Scoped to this hotel's own room types, not any hotel's.
            'contractRoomTypeId' => ['required', 'integer', Rule::exists('room_types', 'id')->where('hotel_id', $hotel->id)],
            'contractType' => ['required', new \Illuminate\Validation\Rules\Enum(HotelContractType::class)],
            'contractStartsOn' => ['required', 'date'],
            'contractEndsOn' => ['required', 'date', 'after_or_equal:contractStartsOn'],
            'contractReleaseDays' => ['nullable', 'integer', 'min:0'],
            'ratePurchase' => ['required', 'numeric', 'min:0'],
            'rateSale' => ['required', 'numeric', 'min:0'],
        ]);

        $contract = $hotel->contracts()->create([
            'operator_id' => $hotel->operator_id,
            'contract_type' => $validated['contractType'],
            'starts_on' => $validated['contractStartsOn'],
            'ends_on' => $validated['contractEndsOn'],
            'release_days' => $validated['contractReleaseDays'] ?: null,
        ]);

        $contract->rates()->create([
            'room_type_id' => $validated['contractRoomTypeId'],
            'starts_on' => $contract->starts_on,
            'ends_on' => $contract->ends_on,
            'purchase_price_cents' => (int) round($validated['ratePurchase'] * 100),
            'sale_price_cents' => (int) round($validated['rateSale'] * 100),
        ]);

        $this->reset(['contractRoomTypeId', 'contractType', 'contractStartsOn', 'contractEndsOn', 'ratePurchase', 'rateSale']);
        $this->contractReleaseDays = '14';
        \Flux\Flux::toast(text: __('Contract created.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="$this->hotel->name" :subtitle="$this->hotel->city->label().' · '.($this->hotel->stars ? $this->hotel->stars.' '.__('Stars') : __('unrated'))">
        <x-slot name="actions">
            <flux:badge size="lg" :color="$this->hotel->isConfirmed() ? 'lime' : 'amber'">
                {{ $this->hotel->isConfirmed() ? __('Confirmed') : __('Pending confirmation') }}
            </flux:badge>
            <flux:button size="sm" variant="ghost" icon="calendar-days" :href="route('hotels.availability', $this->hotel)" wire:navigate>{{ __('Availability') }}</flux:button>
            @if ($this->canManage && ! $this->hotel->isConfirmed())
                <flux:button size="sm" variant="primary" wire:click="confirmHotel">{{ __('Confirm') }}</flux:button>
            @endif
        </x-slot>
    </x-ui.page-header>

    {{-- Room types --}}
    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Room Types') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Bed capacity') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->hotel->roomTypes as $roomType)
                    <flux:table.row wire:key="room-type-{{ $roomType->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $roomType->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Bed capacity')">{{ $roomType->capacity }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2">
                            <x-ui.empty-state icon="home-modern" :title="__('No room types yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="addRoomType" class="flex flex-wrap items-end gap-4 p-6 pt-4">
            <flux:input wire:model="roomTypeName" :label="__('Name')" placeholder="Quad" class="max-w-48" />
            <flux:input type="number" min="1" max="12" wire:model="roomTypeCapacity" :label="__('Bed capacity')" class="max-w-32" />
            <flux:button type="submit" size="sm">{{ __('Add') }}</flux:button>
        </form>
    </x-ui.card>

    {{-- Contracts --}}
    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Contracts') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Dates') }}</flux:table.column>
                <flux:table.column minor>{{ __('Release deadline') }}</flux:table.column>
                <flux:table.column>{{ __('Rates (per bed/night)') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->hotel->contracts as $contract)
                    <flux:table.row wire:key="contract-{{ $contract->id }}">
                        <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $contract->contract_type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell :label="__('Dates')">{{ $contract->starts_on->format('Y-m-d') }} &rarr; {{ $contract->ends_on->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Release deadline')">{{ $contract->releaseDeadline()?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('Rates (per bed/night)')">
                            @foreach ($contract->rates as $rate)
                                {{ $rate->roomType->name }}: {{ number_format($rate->purchase_price_cents / 100, 2) }} / {{ number_format($rate->sale_price_cents / 100, 2) }} {{ $contract->currency }}<br>
                            @endforeach
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <x-ui.empty-state icon="document-text" :title="__('No contracts yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="addContract" class="m-6 mt-4 grid grid-cols-2 gap-4 rounded-xl border border-zinc-200 p-4 sm:grid-cols-4 dark:border-white/10">
            <flux:select wire:model="contractRoomTypeId" :label="__('Room type')">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach ($this->hotel->roomTypes as $roomType)
                    <flux:select.option value="{{ $roomType->id }}">{{ $roomType->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="contractType" :label="__('Contract type')">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach (\App\Enums\HotelContractType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model="contractStartsOn" :label="__('Starts')" />
            <flux:input type="date" wire:model="contractEndsOn" :label="__('Ends')" />
            <flux:input type="number" min="0" wire:model="contractReleaseDays" :label="__('Release deadline (days before)')" />
            <flux:input type="number" step="0.01" min="0" wire:model="ratePurchase" :label="__('Purchase price / bed / night')" />
            <flux:input type="number" step="0.01" min="0" wire:model="rateSale" :label="__('Sale price / bed / night')" />
            <div class="flex items-end">
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </x-ui.card>
</div>
