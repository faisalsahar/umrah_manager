<?php

use App\Enums\LocationType;
use App\Enums\OrganizationType;
use App\Livewire\Concerns\GuardsDestructiveDeletes;
use App\Models\City;
use App\Models\Location;
use App\Models\Movement;
use App\Models\OperatingOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Geography')] class extends Component {
    use GuardsDestructiveDeletes;

    // Which kind of row the shared confirm-delete modal is currently targeting —
    // this page has two independent deletable catalogs (cities and locations).
    public string $deletingKind = '';

    public ?int $editingCityId = null;

    public string $newCityNameAr = '';

    public string $newCityNameEn = '';

    public bool $newCityIsPort = false;

    public bool $newCityIsAccommodation = false;

    public bool $newCityHasTrain = false;

    public ?int $editingLocationId = null;

    public string $newLocationNameAr = '';

    public string $newLocationNameEn = '';

    public string $newLocationType = '';

    public string $newLocationCityId = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages its geography catalogue. Switch to your operator organization first.'));
    }

    public function getCitiesProperty()
    {
        return City::where('operator_id', Auth::user()->current_organization_id)
            ->withCount('locations')
            ->orderBy('name_en')
            ->get();
    }

    public function getLocationsProperty()
    {
        return Location::where('operator_id', Auth::user()->current_organization_id)
            ->with('city')
            ->orderBy('type')
            ->orderBy('name_en')
            ->get();
    }

    public function editCity(int $cityId): void
    {
        $city = City::where('operator_id', Auth::user()->current_organization_id)->find($cityId);
        abort_if($city === null, 404);

        $this->editingCityId = $city->id;
        $this->newCityNameAr = $city->name_ar;
        $this->newCityNameEn = $city->name_en;
        $this->newCityIsPort = $city->is_port;
        $this->newCityIsAccommodation = $city->is_accommodation;
        $this->newCityHasTrain = $city->has_train;
    }

    public function cancelEditCity(): void
    {
        $this->reset(['editingCityId', 'newCityNameAr', 'newCityNameEn', 'newCityIsPort', 'newCityIsAccommodation', 'newCityHasTrain']);
    }

    public function saveCity(): void
    {
        $validated = $this->validate([
            'newCityNameAr' => ['required', 'string', 'max:255'],
            'newCityNameEn' => ['required', 'string', 'max:255'],
        ]);

        $attributes = [
            'name_ar' => $validated['newCityNameAr'],
            'name_en' => $validated['newCityNameEn'],
            'is_port' => $this->newCityIsPort,
            'is_accommodation' => $this->newCityIsAccommodation,
            'has_train' => $this->newCityHasTrain,
        ];

        if ($this->editingCityId) {
            $city = City::where('operator_id', Auth::user()->current_organization_id)->find($this->editingCityId);
            abort_if($city === null, 404);
            $city->update($attributes);
            \Flux\Flux::toast(text: __('City updated.'), variant: 'success');
        } else {
            City::create(['operator_id' => Auth::user()->current_organization_id, ...$attributes]);
            \Flux\Flux::toast(text: __('City added.'), variant: 'success');
        }

        $this->reset(['editingCityId', 'newCityNameAr', 'newCityNameEn', 'newCityIsPort', 'newCityIsAccommodation', 'newCityHasTrain']);
    }

    /**
     * A city's dependents span both its locations (which lose their city link)
     * and any operating order that names it as an arrival/departure city.
     */
    public function confirmDeleteCity(int $cityId): void
    {
        $this->deletingKind = 'city';

        $dependents = Location::where('city_id', $cityId)->count()
            + OperatingOrder::where('arrival_city_id', $cityId)->orWhere('departure_city_id', $cityId)->count();

        $this->confirmDelete($cityId, $dependents);
    }

    public function editLocation(int $locationId): void
    {
        $location = Location::where('operator_id', Auth::user()->current_organization_id)->find($locationId);
        abort_if($location === null, 404);

        $this->editingLocationId = $location->id;
        $this->newLocationNameAr = $location->name_ar;
        $this->newLocationNameEn = $location->name_en;
        $this->newLocationType = $location->type->value;
        $this->newLocationCityId = $location->city_id ? (string) $location->city_id : '';
    }

    public function cancelEditLocation(): void
    {
        $this->reset(['editingLocationId', 'newLocationNameAr', 'newLocationNameEn', 'newLocationType', 'newLocationCityId']);
    }

    public function saveLocation(): void
    {
        $operatorId = Auth::user()->current_organization_id;

        $validated = $this->validate([
            'newLocationNameAr' => ['required', 'string', 'max:255'],
            'newLocationNameEn' => ['required', 'string', 'max:255'],
            'newLocationType' => ['required', new \Illuminate\Validation\Rules\Enum(LocationType::class)],
            // Scoped to this operator — otherwise a tampered request could link a
            // location to another operator's city.
            'newLocationCityId' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('operator_id', $operatorId)],
        ]);

        $attributes = [
            'city_id' => $validated['newLocationCityId'] ?: null,
            'type' => $validated['newLocationType'],
            'name_ar' => $validated['newLocationNameAr'],
            'name_en' => $validated['newLocationNameEn'],
        ];

        if ($this->editingLocationId) {
            $location = Location::where('operator_id', $operatorId)->find($this->editingLocationId);
            abort_if($location === null, 404);
            $location->update($attributes);
            \Flux\Flux::toast(text: __('Location updated.'), variant: 'success');
        } else {
            Location::create(['operator_id' => $operatorId, ...$attributes]);
            \Flux\Flux::toast(text: __('Location added.'), variant: 'success');
        }

        $this->reset(['editingLocationId', 'newLocationNameAr', 'newLocationNameEn', 'newLocationType', 'newLocationCityId']);
    }

    public function confirmDeleteLocation(int $locationId): void
    {
        $this->deletingKind = 'location';

        $dependents = Movement::where('stopover_location_id', $locationId)->count()
            + OperatingOrder::where('arrival_port_location_id', $locationId)->orWhere('departure_port_location_id', $locationId)->count();

        $this->confirmDelete($locationId, $dependents);
    }

    public function deleteConfirmed(): void
    {
        $operatorId = Auth::user()->current_organization_id;
        $kind = $this->deletingKind;

        $this->guardedDelete(function (int $id) use ($operatorId, $kind): void {
            $model = $kind === 'city'
                ? City::where('operator_id', $operatorId)->find($id)
                : Location::where('operator_id', $operatorId)->find($id);

            abort_if($model === null, 404);
            $model->delete();
        });
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header
        :title="__('Geography')"
        :subtitle="__('The cities, ports, stations, and sites your orders and movements reference — instead of free-text locations.')"
    />

    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Cities') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Port') }}</flux:table.column>
                <flux:table.column>{{ __('Accommodation') }}</flux:table.column>
                <flux:table.column>{{ __('Train') }}</flux:table.column>
                <flux:table.column minor>{{ __('Locations') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->cities as $city)
                    <flux:table.row wire:key="city-{{ $city->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $city->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Port')">{{ $city->is_port ? __('Yes') : __('No') }}</flux:table.cell>
                        <flux:table.cell :label="__('Accommodation')">{{ $city->is_accommodation ? __('Yes') : __('No') }}</flux:table.cell>
                        <flux:table.cell :label="__('Train')">{{ $city->has_train ? __('Yes') : __('No') }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Locations')">{{ $city->locations_count }}</flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="editCity({{ $city->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDeleteCity({{ $city->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="map" :title="__('No cities yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="saveCity" class="m-6 flex flex-wrap items-end gap-4 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
            <flux:input wire:model="newCityNameAr" :label="__('Name (Arabic)')" class="max-w-48" />
            <flux:input wire:model="newCityNameEn" :label="__('Name (English)')" class="max-w-48" />
            <flux:checkbox wire:model="newCityIsPort" :label="__('Port')" />
            <flux:checkbox wire:model="newCityIsAccommodation" :label="__('Accommodation')" />
            <flux:checkbox wire:model="newCityHasTrain" :label="__('Train')" />
            <flux:button type="submit" variant="primary">{{ $editingCityId ? __('Save') : __('Add') }}</flux:button>
            @if ($editingCityId)
                <flux:button variant="ghost" wire:click="cancelEditCity">{{ __('Cancel') }}</flux:button>
            @endif
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Locations') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('City') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->locations as $location)
                    <flux:table.row wire:key="location-{{ $location->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $location->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $location->type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell :label="__('City')">{{ $location->city?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="editLocation({{ $location->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDeleteLocation({{ $location->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <x-ui.empty-state icon="map-pin" :title="__('No locations yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="saveLocation" class="m-6 grid grid-cols-2 gap-4 rounded-xl border border-zinc-200 p-4 sm:grid-cols-4 dark:border-white/10">
            <flux:input wire:model="newLocationNameAr" :label="__('Name (Arabic)')" />
            <flux:input wire:model="newLocationNameEn" :label="__('Name (English)')" />
            <flux:select wire:model="newLocationType" :label="__('Type')">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach (\App\Enums\LocationType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="newLocationCityId" :label="__('City')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->cities as $city)
                    <flux:select.option value="{{ $city->id }}">{{ $city->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="col-span-full flex justify-end gap-3">
                @if ($editingLocationId)
                    <flux:button variant="ghost" wire:click="cancelEditLocation">{{ __('Cancel') }}</flux:button>
                @endif
                <flux:button type="submit" variant="primary">{{ $editingLocationId ? __('Save') : __('Add') }}</flux:button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.confirm-delete-modal :deleting-id="$deletingId" :deleting-dependents="$deletingDependents" :math-a="$mathA" :math-b="$mathB" />
</div>
