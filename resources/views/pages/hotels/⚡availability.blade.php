<?php

use App\Enums\HotelStayStatus;
use App\Models\ContractAllotment;
use App\Models\Hotel;
use App\Models\HotelStay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hotel Availability')] class extends Component {
    #[Locked]
    public int $hotelId;

    public string $startsOn = '';

    public string $endsOn = '';

    public ?int $detailRoomTypeId = null;

    public ?string $detailDate = null;

    /**
     * Which room type the mobile layout's vertical night list shows — the
     * table version shows every room type at once, but that only works with
     * room to scroll sideways, so mobile picks one at a time instead.
     */
    public ?int $mobileRoomTypeId = null;

    public function mount(Hotel $hotel): void
    {
        abort_unless(Auth::user()->belongsToOrganization($hotel->operator), 403);

        $this->hotelId = $hotel->id;
        $this->startsOn = now()->format('Y-m-d');
        $this->endsOn = now()->addDays(13)->format('Y-m-d');
        $this->mobileRoomTypeId = $hotel->roomTypes->first()?->id;
    }

    public function getHotelProperty()
    {
        return Hotel::with('roomTypes')->findOrFail($this->hotelId);
    }

    public function shiftRange(int $days): void
    {
        $this->startsOn = Carbon::parse($this->startsOn)->addDays($days)->format('Y-m-d');
        $this->endsOn = Carbon::parse($this->endsOn)->addDays($days)->format('Y-m-d');
    }

    /**
     * The nights this grid covers — falls back to a safe 14-night window
     * whenever the picked range is invalid or unreasonably wide, so a typo in
     * the date fields can never blow up the grid to thousands of columns.
     *
     * @return list<Carbon>
     */
    public function getNightsProperty(): array
    {
        $start = Carbon::parse($this->startsOn);
        $end = Carbon::parse($this->endsOn);

        if ($end->lt($start) || $start->diffInDays($end) > 60) {
            $start = now()->startOfDay();
            $end = $start->copy()->addDays(13);
        }

        $nights = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $nights[] = $date->copy();
        }

        return $nights;
    }

    /**
     * [room_type_id][Y-m-d] => allotted/occupied counts for that room type, that
     * night. `occupied_rooms` is an estimate (occupied beds ÷ capacity, rounded
     * up) since a stay records a pilgrim, not a specific physical room — see
     * ContractAllotment's docblock for why an exact room-level count isn't
     * tracked. Cancelled stays never count as occupying a bed.
     *
     * @return array<int, array<string, array{allotted: int, occupied_beds: int, occupied_rooms: int}>>
     */
    public function getGridProperty(): array
    {
        $nights = $this->nights;
        $start = $nights[0];
        $end = end($nights);

        $allotments = ContractAllotment::query()
            ->whereHas('hotelContract', fn ($query) => $query->where('hotel_id', $this->hotelId))
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->groupBy(fn (ContractAllotment $allotment) => $allotment->room_type_id.'|'.$allotment->date->format('Y-m-d'));

        $stays = HotelStay::query()
            ->where('hotel_id', $this->hotelId)
            ->where('status', '!=', HotelStayStatus::Cancelled)
            ->where('check_in', '<', $end->copy()->addDay())
            ->where('check_out', '>', $start)
            ->get()
            ->groupBy('room_type_id');

        $grid = [];

        foreach ($this->hotel->roomTypes as $roomType) {
            $roomStays = $stays->get($roomType->id, collect());

            foreach ($nights as $night) {
                $key = $roomType->id.'|'.$night->format('Y-m-d');
                $allotted = ($allotments->get($key) ?? collect())->sum('rooms_allotted');
                $occupiedBeds = $roomStays
                    ->filter(fn (HotelStay $stay): bool => $stay->check_in->lte($night) && $stay->check_out->gt($night))
                    ->count();
                $occupiedRooms = $roomType->capacity > 0 ? (int) ceil($occupiedBeds / $roomType->capacity) : $occupiedBeds;

                $grid[$roomType->id][$night->format('Y-m-d')] = [
                    'allotted' => $allotted,
                    'occupied_beds' => $occupiedBeds,
                    'occupied_rooms' => $occupiedRooms,
                ];
            }
        }

        return $grid;
    }

    public function showDetail(int $roomTypeId, string $date): void
    {
        $this->detailRoomTypeId = $roomTypeId;
        $this->detailDate = $date;
    }

    public function closeDetail(): void
    {
        $this->detailRoomTypeId = null;
        $this->detailDate = null;
    }

    public function getDetailRoomTypeProperty()
    {
        if ($this->detailRoomTypeId === null) {
            return null;
        }

        return $this->hotel->roomTypes->firstWhere('id', $this->detailRoomTypeId);
    }

    public function getMobileRoomTypeProperty()
    {
        return $this->hotel->roomTypes->firstWhere('id', $this->mobileRoomTypeId)
            ?? $this->hotel->roomTypes->first();
    }

    /**
     * Every pilgrim occupying a bed of this room type on this specific night —
     * what a click on a grid cell is meant to reveal.
     */
    public function getDetailStaysProperty()
    {
        if ($this->detailRoomTypeId === null || $this->detailDate === null) {
            return collect();
        }

        $night = Carbon::parse($this->detailDate);

        return HotelStay::query()
            ->where('hotel_id', $this->hotelId)
            ->where('room_type_id', $this->detailRoomTypeId)
            ->where('status', '!=', HotelStayStatus::Cancelled)
            ->where('check_in', '<=', $night)
            ->where('check_out', '>', $night)
            ->with(['pilgrim', 'operatingOrder'])
            ->orderBy('check_in')
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Availability — :hotel', ['hotel' => $this->hotel->name])" :subtitle="__('Click any night to see who is booked into it.')">
        <x-slot name="actions">
            <flux:button size="sm" variant="ghost" :href="route('hotels.show', $this->hotel)" wire:navigate>{{ __('Back') }}</flux:button>
        </x-slot>
    </x-ui.page-header>

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <flux:input type="date" wire:model.live="startsOn" :label="__('From')" class="max-w-40 flex-1 sm:flex-none" />
            <flux:input type="date" wire:model.live="endsOn" :label="__('To')" class="max-w-40 flex-1 sm:flex-none" />
            <div class="flex gap-2">
                <flux:button size="sm" variant="ghost" wire:click="shiftRange(-14)" class="flex-1 sm:flex-none">{{ __('← 14 nights') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="shiftRange(14)" class="flex-1 sm:flex-none">{{ __('14 nights →') }}</flux:button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-zinc-500">
            <span class="flex items-center gap-1.5"><span class="size-3 shrink-0 rounded bg-lime-400"></span>{{ __('Available') }}</span>
            <span class="flex items-center gap-1.5"><span class="size-3 shrink-0 rounded bg-amber-400"></span>{{ __('Full') }}</span>
            <span class="flex items-center gap-1.5"><span class="size-3 shrink-0 rounded bg-red-400"></span>{{ __('Overbooked') }}</span>
            <span class="flex items-center gap-1.5"><span class="size-3 shrink-0 rounded bg-zinc-300 dark:bg-zinc-700"></span>{{ __('No allotment') }}</span>
        </div>
    </div>

    @if ($this->hotel->roomTypes->isEmpty())
        <x-ui.card class="text-center">
            <x-ui.empty-state icon="home-modern" :title="__('No room types yet.')" />
        </x-ui.card>
    @else
        {{-- Mobile: one room type at a time, as a tall vertical list — a wide
             table only works when there's room to scroll sideways. --}}
        <div class="flex flex-col gap-3 sm:hidden">
            @if ($this->hotel->roomTypes->count() > 1)
                <flux:select wire:model.live="mobileRoomTypeId" :label="__('Room type')">
                    @foreach ($this->hotel->roomTypes as $roomType)
                        <flux:select.option value="{{ $roomType->id }}">{{ $roomType->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <x-ui.card :padded="false">
                <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                    @foreach ($this->nights as $night)
                        @php
                            $roomType = $this->mobileRoomType;
                            $dateKey = $night->format('Y-m-d');
                            $cell = $this->grid[$roomType->id][$dateKey] ?? ['allotted' => 0, 'occupied_beds' => 0, 'occupied_rooms' => 0];
                            $color = match (true) {
                                $cell['allotted'] === 0 => 'zinc',
                                $cell['occupied_rooms'] > $cell['allotted'] => 'red',
                                $cell['occupied_rooms'] === $cell['allotted'] => 'amber',
                                default => 'lime',
                            };
                            $dot = match ($color) {
                                'zinc' => 'bg-zinc-300 dark:bg-zinc-700',
                                'red' => 'bg-red-400',
                                'amber' => 'bg-amber-400',
                                default => 'bg-lime-400',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:click="showDetail({{ $roomType->id }}, '{{ $dateKey }}')"
                            class="flex w-full items-center justify-between gap-3 p-4 text-start active:bg-zinc-50 dark:active:bg-zinc-800"
                        >
                            <span>
                                <span class="block font-medium">{{ $night->format('D, M j') }}</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full {{ $dot }}"></span>
                                @if ($cell['allotted'] > 0)
                                    <span class="text-sm text-zinc-500">{{ $cell['occupied_rooms'] }}/{{ $cell['allotted'] }}</span>
                                @else
                                    <span class="text-sm text-zinc-400">{{ __('No allotment') }}</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        {{-- Desktop/tablet: every room type visible at once, nights across the top. --}}
        <x-ui.card :padded="false" class="hidden overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch] sm:block">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="sticky start-0 z-10 min-w-40 border-b border-zinc-200 bg-white p-3 text-start font-medium text-zinc-500 dark:border-white/10 dark:bg-zinc-900">
                            {{ __('Room type') }}
                        </th>
                        @foreach ($this->nights as $night)
                            <th class="min-w-16 border-b border-s border-zinc-200 p-2 text-center text-xs font-medium text-zinc-500 dark:border-white/10">
                                {{ $night->format('D') }}<br>{{ $night->format('M j') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->hotel->roomTypes as $roomType)
                        <tr wire:key="grid-room-{{ $roomType->id }}">
                            <td class="sticky start-0 z-10 border-b border-zinc-200 bg-white p-3 text-sm font-medium dark:border-white/10 dark:bg-zinc-900">
                                {{ $roomType->name }}
                                <span class="block text-xs font-normal text-zinc-500">{{ __(':count beds/room', ['count' => $roomType->capacity]) }}</span>
                            </td>
                            @foreach ($this->nights as $night)
                                @php
                                    $dateKey = $night->format('Y-m-d');
                                    $cell = $this->grid[$roomType->id][$dateKey] ?? ['allotted' => 0, 'occupied_beds' => 0, 'occupied_rooms' => 0];
                                    $color = match (true) {
                                        $cell['allotted'] === 0 => 'zinc',
                                        $cell['occupied_rooms'] > $cell['allotted'] => 'red',
                                        $cell['occupied_rooms'] === $cell['allotted'] => 'amber',
                                        default => 'lime',
                                    };
                                    $bg = match ($color) {
                                        'zinc' => 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400',
                                        'red' => 'bg-red-100 hover:bg-red-200 dark:bg-red-950 dark:hover:bg-red-900 text-red-700 dark:text-red-300',
                                        'amber' => 'bg-amber-100 hover:bg-amber-200 dark:bg-amber-950 dark:hover:bg-amber-900 text-amber-700 dark:text-amber-300',
                                        default => 'bg-lime-100 hover:bg-lime-200 dark:bg-lime-950 dark:hover:bg-lime-900 text-lime-700 dark:text-lime-300',
                                    };
                                @endphp
                                <td class="border-b border-s border-zinc-200 p-0 dark:border-white/10">
                                    <button
                                        type="button"
                                        wire:click="showDetail({{ $roomType->id }}, '{{ $dateKey }}')"
                                        title="{{ __(':occupied / :allotted rooms', ['occupied' => $cell['occupied_rooms'], 'allotted' => $cell['allotted']]) }}"
                                        class="flex h-14 w-full flex-col items-center justify-center gap-0.5 text-xs font-semibold transition-colors {{ $bg }}"
                                    >
                                        @if ($cell['allotted'] > 0)
                                            <span>{{ $cell['occupied_rooms'] }}/{{ $cell['allotted'] }}</span>
                                        @else
                                            <span>—</span>
                                        @endif
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif

    <flux:modal :show="$detailRoomTypeId !== null" @close="closeDetail" focusable class="max-w-lg">
        @if ($detailRoomTypeId !== null)
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ $this->detailRoomType?->name }} &middot; {{ $detailDate }}</flux:heading>
                    <flux:subheading>
                        {{ __(':count bed(s) occupied that night.', ['count' => $this->detailStays->count()]) }}
                    </flux:subheading>
                </div>

                @if ($this->detailStays->isEmpty())
                    <x-ui.empty-state icon="home-modern" :title="__('No one booked into this room type that night.')" />
                @else
                    <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                        @foreach ($this->detailStays as $stay)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div>
                                    <div class="font-medium">{{ $stay->pilgrim->name }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $stay->pilgrim->passport_number }}
                                        @if ($stay->operatingOrder)
                                            &middot; {{ $stay->operatingOrder->reference_no }}
                                        @endif
                                        &middot; {{ $stay->check_in->format('Y-m-d') }} → {{ $stay->check_out->format('Y-m-d') }}
                                    </div>
                                </div>
                                <flux:badge size="sm">{{ $stay->status->label() }}</flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
