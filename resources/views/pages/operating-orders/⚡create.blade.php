<?php

use App\Enums\LocationType;
use App\Enums\OrganizationType;
use App\Enums\TripMode;
use App\Models\City;
use App\Models\Group;
use App\Models\Location;
use App\Models\OperatingOrder;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Operating Order')] class extends Component {
    public string $agentId = '';

    public string $groupId = '';

    public string $adultsCount = '';

    public string $childrenCount = '';

    public string $infantsCount = '';

    public string $arrivalDate = '';

    public string $arrivalTripMode = '';

    public string $arrivalCityId = '';

    public string $arrivalPortLocationId = '';

    public string $arrivalCarrier = '';

    public string $arrivalFlightNo = '';

    public string $arrivalTime = '';

    public string $departureDate = '';

    public string $departureTripMode = '';

    public string $departureCityId = '';

    public string $departurePortLocationId = '';

    public string $departureCarrier = '';

    public string $departureFlightNo = '';

    public string $departureTime = '';

    public string $notes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator can open an operating order. Switch to your operator organization first.'));
    }

    /**
     * Every agent organization, including sub-agents (an agent with a parent
     * organization) — an operator can take an order from any of them.
     */
    public function getAgentsProperty()
    {
        return Organization::ofType(OrganizationType::Agent)->with('parent')->orderBy('name_en')->get();
    }

    public function getGroupsProperty()
    {
        $operator = Auth::user()->currentOrganization;

        return Group::where('operator_id', $operator->id)
            ->when($this->agentId !== '', fn ($query) => $query->where('agent_id', $this->agentId))
            ->orderByDesc('arrival_date')
            ->get();
    }

    public function getCitiesProperty()
    {
        return City::where('operator_id', Auth::user()->current_organization_id)->orderBy('name_en')->get();
    }

    public function getArrivalPortsProperty()
    {
        return $this->portsFor($this->arrivalTripMode);
    }

    public function getDeparturePortsProperty()
    {
        return $this->portsFor($this->departureTripMode);
    }

    private function portsFor(string $tripMode)
    {
        if ($tripMode === '') {
            return collect();
        }

        return Location::where('operator_id', Auth::user()->current_organization_id)
            ->ofType(LocationType::portsFor(TripMode::from($tripMode)))
            ->orderBy('name_en')
            ->get();
    }

    public function save(): void
    {
        $operator = Auth::user()->currentOrganization;

        $validated = $this->validate([
            // Scoped to type=Agent and to this operator's own groups — otherwise a
            // tampered request could link the order to another operator's group and
            // leak its pilgrims' names and passport numbers onto this screen.
            'agentId' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('type', OrganizationType::Agent->value)],
            'groupId' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('operator_id', $operator->id)],
            'adultsCount' => ['nullable', 'integer', 'min:0'],
            'childrenCount' => ['nullable', 'integer', 'min:0'],
            'infantsCount' => ['nullable', 'integer', 'min:0'],
            'arrivalDate' => ['nullable', 'date'],
            'arrivalTripMode' => ['nullable', new \Illuminate\Validation\Rules\Enum(TripMode::class)],
            'arrivalCityId' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('operator_id', $operator->id)],
            'arrivalPortLocationId' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('operator_id', $operator->id)],
            'arrivalCarrier' => ['nullable', 'string', 'max:255'],
            'arrivalFlightNo' => ['nullable', 'string', 'max:255'],
            'arrivalTime' => ['nullable', 'date_format:H:i'],
            'departureDate' => ['nullable', 'date'],
            'departureTripMode' => ['nullable', new \Illuminate\Validation\Rules\Enum(TripMode::class)],
            'departureCityId' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('operator_id', $operator->id)],
            'departurePortLocationId' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('operator_id', $operator->id)],
            'departureCarrier' => ['nullable', 'string', 'max:255'],
            'departureFlightNo' => ['nullable', 'string', 'max:255'],
            'departureTime' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = OperatingOrder::create([
            'operator_id' => $operator->id,
            'agent_id' => $validated['agentId'] ?: null,
            'group_id' => $validated['groupId'] ?: null,
            'entered_by_user_id' => Auth::id(),
            'adults_count' => $validated['adultsCount'] !== null && $validated['adultsCount'] !== '' ? $validated['adultsCount'] : null,
            'children_count' => $validated['childrenCount'] !== null && $validated['childrenCount'] !== '' ? $validated['childrenCount'] : null,
            'infants_count' => $validated['infantsCount'] !== null && $validated['infantsCount'] !== '' ? $validated['infantsCount'] : null,
            'arrival_date' => $validated['arrivalDate'] ?: null,
            'arrival_trip_mode' => $validated['arrivalTripMode'] ?: null,
            'arrival_city_id' => $validated['arrivalCityId'] ?: null,
            'arrival_port_location_id' => $validated['arrivalPortLocationId'] ?: null,
            'arrival_carrier' => $validated['arrivalCarrier'] ?: null,
            'arrival_flight_no' => $validated['arrivalFlightNo'] ?: null,
            'arrival_time' => $validated['arrivalTime'] ?: null,
            'departure_date' => $validated['departureDate'] ?: null,
            'departure_trip_mode' => $validated['departureTripMode'] ?: null,
            'departure_city_id' => $validated['departureCityId'] ?: null,
            'departure_port_location_id' => $validated['departurePortLocationId'] ?: null,
            'departure_carrier' => $validated['departureCarrier'] ?: null,
            'departure_flight_no' => $validated['departureFlightNo'] ?: null,
            'departure_time' => $validated['departureTime'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

        \Flux\Flux::toast(text: __(':reference created.', ['reference' => $order->reference_no]), variant: 'success');

        $this->redirect(route('operating-orders.show', $order), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('New Operating Order')" :subtitle="__('One entry here links the client, the group, and — once created — every hotel and transport line that follows.')" />

    <x-ui.card class="max-w-3xl">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:select wire:model.live="agentId" :label="__('Agent (client)')">
                <flux:select.option value="">{{ __('— Direct booking (no external agent) —') }}</flux:select.option>
                @foreach ($this->agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">
                        {{ $agent->name }}{{ $agent->parent ? ' — '.__('sub-agent of :name', ['name' => $agent->parent->name]) : '' }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="groupId" :label="__('Group')">
                <flux:select.option value="">{{ __('— No group yet —') }}</flux:select.option>
                @foreach ($this->groups as $group)
                    <flux:select.option value="{{ $group->id }}">
                        {{ $group->name }} ({{ $group->arrival_date?->format('Y-m-d') }})
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-3 gap-4">
                <flux:input type="number" min="0" wire:model="adultsCount" :label="__('Adults')" />
                <flux:input type="number" min="0" wire:model="childrenCount" :label="__('Children')" />
                <flux:input type="number" min="0" wire:model="infantsCount" :label="__('Infants')" />
            </div>

            <flux:separator :text="__('Arrival')" />
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <flux:input type="date" wire:model="arrivalDate" :label="__('Arrival date')" />
                <flux:select wire:model.live="arrivalTripMode" :label="__('Trip mode')">
                    <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                    @foreach (\App\Enums\TripMode::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="arrivalCityId" :label="__('City')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->cities as $city)
                        <flux:select.option value="{{ $city->id }}">{{ $city->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="arrivalPortLocationId" :label="__('Port')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->arrivalPorts as $port)
                        <flux:select.option value="{{ $port->id }}">{{ $port->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="arrivalCarrier" :label="__('Carrier')" placeholder="Saudia" />
                <flux:input wire:model="arrivalFlightNo" :label="__('Flight / trip no.')" placeholder="SV 799" />
                <flux:input type="time" wire:model="arrivalTime" :label="__('Time')" />
            </div>

            <flux:separator :text="__('Departure')" />
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <flux:input type="date" wire:model="departureDate" :label="__('Departure date')" />
                <flux:select wire:model.live="departureTripMode" :label="__('Trip mode')">
                    <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                    @foreach (\App\Enums\TripMode::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="departureCityId" :label="__('City')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->cities as $city)
                        <flux:select.option value="{{ $city->id }}">{{ $city->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="departurePortLocationId" :label="__('Port')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->departurePorts as $port)
                        <flux:select.option value="{{ $port->id }}">{{ $port->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="departureCarrier" :label="__('Carrier')" placeholder="Saudia" />
                <flux:input wire:model="departureFlightNo" :label="__('Flight / trip no.')" placeholder="SV 802" />
                <flux:input type="time" wire:model="departureTime" :label="__('Time')" />
            </div>

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

            <div class="flex justify-end gap-3">
                <flux:button :href="route('operating-orders.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </x-ui.card>
</div>
