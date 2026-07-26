<?php

use App\Enums\HotelCity;
use App\Enums\OrganizationType;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hotels')] class extends Component {
    public string $newNameAr = '';

    public string $newNameEn = '';

    public string $newCity = '';

    public string $newStars = '';

    public bool $showArchived = false;

    public string $bulkRows = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages hotels. Switch to your operator organization first.'));
    }

    public function getCanManageProperty(): bool
    {
        $current = Auth::user()->currentOrganization;

        return $current && Auth::user()->roleIn($current)?->managesOrganization();
    }

    public function getHotelsProperty()
    {
        $query = $this->showArchived
            ? Hotel::onlyTrashed()
            : Hotel::query();

        return $query->where('operator_id', Auth::user()->current_organization_id)
            ->withCount(['roomTypes', 'contracts'])
            ->orderBy('city')
            ->orderBy('name_en')
            ->get();
    }

    public function createHotel(): void
    {
        $validated = $this->validate([
            'newNameAr' => ['required', 'string', 'max:255'],
            'newNameEn' => ['required', 'string', 'max:255'],
            'newCity' => ['required', new \Illuminate\Validation\Rules\Enum(HotelCity::class)],
            'newStars' => ['nullable', 'integer', 'min:1', 'max:7'],
        ]);

        Hotel::create([
            'operator_id' => Auth::user()->current_organization_id,
            'name_ar' => $validated['newNameAr'],
            'name_en' => $validated['newNameEn'],
            'city' => $validated['newCity'],
            'stars' => $validated['newStars'] ?: null,
        ]);

        $this->reset(['newNameAr', 'newNameEn', 'newCity', 'newStars']);
        \Flux\Flux::toast(text: __('Hotel added.'), variant: 'success');
    }

    /**
     * One hotel per line: "Name (Arabic), Name (English), City code[, Stars]" — lets
     * an operator onboard a whole contract's worth of hotels in a single paste
     * instead of one form submission per hotel.
     */
    public function bulkAddHotels(): void
    {
        $operatorId = Auth::user()->current_organization_id;
        $created = 0;

        foreach (explode("\n", $this->bulkRows) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $columns = array_map('trim', explode(',', $line));

            if (count($columns) < 3) {
                continue;
            }

            [$nameAr, $nameEn, $cityCode] = $columns;
            $stars = isset($columns[3]) && $columns[3] !== '' ? (int) $columns[3] : null;
            $city = HotelCity::tryFrom($cityCode);

            if ($nameAr === '' || $nameEn === '' || $city === null) {
                continue;
            }

            Hotel::create([
                'operator_id' => $operatorId,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'city' => $city,
                'stars' => $stars,
            ]);
            $created++;
        }

        $this->reset(['bulkRows']);
        \Flux\Flux::toast(text: __(':count hotel(s) added.', ['count' => $created]), variant: $created > 0 ? 'success' : 'warning');
    }

    public function confirmHotel(int $hotelId): void
    {
        abort_unless($this->canManage, 403);
        $hotel = Hotel::where('operator_id', Auth::user()->current_organization_id)->find($hotelId);

        abort_if($hotel === null, 404);

        $hotel->confirm(Auth::user());
        \Flux\Flux::toast(text: __('Hotel confirmed.'), variant: 'success');
    }

    public function archiveHotel(int $hotelId): void
    {
        abort_unless($this->canManage, 403);
        $hotel = Hotel::where('operator_id', Auth::user()->current_organization_id)->find($hotelId);

        abort_if($hotel === null, 404);

        $hotel->delete();
        \Flux\Flux::toast(text: __('Hotel archived.'), variant: 'success');
    }

    public function restoreHotel(int $hotelId): void
    {
        abort_unless($this->canManage, 403);
        $hotel = Hotel::onlyTrashed()->where('operator_id', Auth::user()->current_organization_id)->find($hotelId);

        abort_if($hotel === null, 404);

        $hotel->restore();
        \Flux\Flux::toast(text: __('Hotel restored.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Hotels')" :subtitle="__('Every hotel contract, tracked from signing to the last night sold.')">
        <x-slot name="actions">
            <flux:button size="sm" variant="ghost" wire:click="$toggle('showArchived')">
                {{ $showArchived ? __('Show active') : __('Show archived') }}
            </flux:button>
        </x-slot>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('City') }}</flux:table.column>
                <flux:table.column minor>{{ __('Stars') }}</flux:table.column>
                <flux:table.column minor>{{ __('Room types') }}</flux:table.column>
                <flux:table.column minor>{{ __('Contracts') }}</flux:table.column>
                @unless ($showArchived)
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                @endunless
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->hotels as $hotel)
                    <flux:table.row wire:key="hotel-{{ $hotel->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $hotel->name }}</flux:table.cell>
                        <flux:table.cell :label="__('City')">{{ $hotel->city->label() }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Stars')">{{ $hotel->stars ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Room types')">{{ $hotel->room_types_count }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Contracts')">{{ $hotel->contracts_count }}</flux:table.cell>
                        @unless ($showArchived)
                            <flux:table.cell :label="__('Status')">
                                <flux:badge size="sm" :color="$hotel->isConfirmed() ? 'lime' : 'amber'">
                                    {{ $hotel->isConfirmed() ? __('Confirmed') : __('Pending confirmation') }}
                                </flux:badge>
                            </flux:table.cell>
                        @endunless
                        <flux:table.cell class="flex items-center gap-3">
                            @if ($showArchived)
                                @if ($this->canManage)
                                    <flux:button size="sm" variant="ghost" wire:click="restoreHotel({{ $hotel->id }})">{{ __('Restore') }}</flux:button>
                                @endif
                            @else
                                <flux:link :accent="false" :href="route('hotels.show', $hotel)" wire:navigate>{{ __('Manage') }}</flux:link>
                                <flux:link :accent="false" :href="route('hotels.availability', $hotel)" wire:navigate>{{ __('Availability') }}</flux:link>
                                @if ($this->canManage)
                                    @unless ($hotel->isConfirmed())
                                        <flux:button size="sm" variant="ghost" wire:click="confirmHotel({{ $hotel->id }})">{{ __('Confirm') }}</flux:button>
                                    @endunless
                                    <flux:button size="sm" variant="ghost" wire:click="archiveHotel({{ $hotel->id }})">{{ __('Archive') }}</flux:button>
                                @endif
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <x-ui.empty-state icon="building-office-2" :title="$showArchived ? __('No archived hotels.') : __('No hotels yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    @unless ($showArchived)
        <x-ui.card>
            <flux:heading size="lg" class="mb-4">{{ __('New hotel') }}</flux:heading>
            <form wire:submit="createHotel" class="flex flex-wrap items-end gap-4">
                <flux:input wire:model="newNameAr" :label="__('Name (Arabic)')" class="max-w-56" />
                <flux:input wire:model="newNameEn" :label="__('Name (English)')" class="max-w-56" />
                <flux:select wire:model="newCity" :label="__('City')" class="max-w-48">
                    <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                    @foreach (\App\Enums\HotelCity::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" min="1" max="7" wire:model="newStars" :label="__('Stars')" class="max-w-24" />
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <flux:heading size="lg" class="mb-2">{{ __('Bulk add hotels') }}</flux:heading>
            <flux:text class="mb-4 text-xs text-zinc-500">
                {{ __('One hotel per line: Name (Arabic), Name (English), City code, Stars (optional). City codes:') }}
                {{ collect(\App\Enums\HotelCity::cases())->map(fn ($case) => $case->value)->implode(', ') }}
            </flux:text>
            <form wire:submit="bulkAddHotels" class="flex flex-col gap-4">
                <flux:textarea wire:model="bulkRows" rows="5" placeholder="فندق الصفوة رويال, Al Safwah Royale, makkah, 5" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                </div>
            </form>
        </x-ui.card>
    @endunless
</div>
