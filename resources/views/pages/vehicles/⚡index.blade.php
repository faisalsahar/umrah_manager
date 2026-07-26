<?php

use App\Enums\OrganizationType;
use App\Livewire\Concerns\GuardsDestructiveDeletes;
use App\Models\Movement;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vehicles')] class extends Component {
    use GuardsDestructiveDeletes;

    // Which kind of row the shared confirm-delete modal is currently targeting —
    // this page has two independent deletable catalogs (types and the fleet
    // itself), so one flag disambiguates deleteConfirmed().
    public string $deletingKind = '';

    public ?int $editingTypeId = null;

    public string $newTypeName = '';

    public string $newTypeCapacity = '';

    public ?int $editingVehicleId = null;

    public string $vehicleTypeId = '';

    public string $plateNo = '';

    public string $driverName = '';

    public string $driverPhone = '';

    public string $transportSupplierId = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages a fleet. Switch to your operator organization first.'));
    }

    public function getVehicleTypesProperty()
    {
        return VehicleType::where('operator_id', Auth::user()->current_organization_id)->orderBy('name')->get();
    }

    public function getVehiclesProperty()
    {
        return Vehicle::where('operator_id', Auth::user()->current_organization_id)
            ->with(['vehicleType', 'transportSupplier'])
            ->orderBy('plate_no')
            ->get();
    }

    public function getTransportSuppliersProperty()
    {
        return Organization::ofType(OrganizationType::TransportSupplier)->orderBy('name_en')->get();
    }

    public function editType(int $typeId): void
    {
        $type = VehicleType::where('operator_id', Auth::user()->current_organization_id)->find($typeId);
        abort_if($type === null, 404);

        $this->editingTypeId = $type->id;
        $this->newTypeName = $type->name;
        $this->newTypeCapacity = (string) $type->capacity;
    }

    public function cancelEditType(): void
    {
        $this->reset(['editingTypeId', 'newTypeName', 'newTypeCapacity']);
    }

    public function saveVehicleType(): void
    {
        $validated = $this->validate([
            'newTypeName' => ['required', 'string', 'max:255'],
            'newTypeCapacity' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        if ($this->editingTypeId) {
            $type = VehicleType::where('operator_id', Auth::user()->current_organization_id)->find($this->editingTypeId);
            abort_if($type === null, 404);
            $type->update(['name' => $validated['newTypeName'], 'capacity' => $validated['newTypeCapacity']]);
            \Flux\Flux::toast(text: __('Vehicle type updated.'), variant: 'success');
        } else {
            VehicleType::create([
                'operator_id' => Auth::user()->current_organization_id,
                'name' => $validated['newTypeName'],
                'capacity' => $validated['newTypeCapacity'],
            ]);
            \Flux\Flux::toast(text: __('Vehicle type added.'), variant: 'success');
        }

        $this->reset(['editingTypeId', 'newTypeName', 'newTypeCapacity']);
    }

    /**
     * Deleting a vehicle type cascades to every vehicle of that type (see the
     * vehicles table's foreign key) — so the dependents count here is what
     * decides whether the math check guards this delete.
     */
    public function confirmDeleteType(int $typeId): void
    {
        $this->deletingKind = 'type';
        $this->confirmDelete($typeId, Vehicle::where('vehicle_type_id', $typeId)->count());
    }

    public function editVehicle(int $vehicleId): void
    {
        $vehicle = Vehicle::where('operator_id', Auth::user()->current_organization_id)->find($vehicleId);
        abort_if($vehicle === null, 404);

        $this->editingVehicleId = $vehicle->id;
        $this->vehicleTypeId = (string) $vehicle->vehicle_type_id;
        $this->plateNo = $vehicle->plate_no ?? '';
        $this->driverName = $vehicle->driver_name ?? '';
        $this->driverPhone = $vehicle->driver_phone ?? '';
        $this->transportSupplierId = $vehicle->transport_supplier_id ? (string) $vehicle->transport_supplier_id : '';
    }

    public function cancelEditVehicle(): void
    {
        $this->reset(['editingVehicleId', 'vehicleTypeId', 'plateNo', 'driverName', 'driverPhone', 'transportSupplierId']);
    }

    public function saveVehicle(): void
    {
        $validated = $this->validate([
            // Scoped to this operator's own vehicle types and to type=TransportSupplier
            // — otherwise a tampered request could cross-link another operator's data.
            'vehicleTypeId' => ['required', 'integer', Rule::exists('vehicle_types', 'id')->where('operator_id', Auth::user()->current_organization_id)],
            'plateNo' => ['nullable', 'string', 'max:32'],
            'driverName' => ['nullable', 'string', 'max:255'],
            'driverPhone' => ['nullable', 'string', 'max:32'],
            'transportSupplierId' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('type', OrganizationType::TransportSupplier->value)],
        ]);

        $attributes = [
            'vehicle_type_id' => $validated['vehicleTypeId'],
            'transport_supplier_id' => $validated['transportSupplierId'] ?: null,
            'plate_no' => $validated['plateNo'] ?: null,
            'driver_name' => $validated['driverName'] ?: null,
            'driver_phone' => $validated['driverPhone'] ?: null,
        ];

        if ($this->editingVehicleId) {
            $vehicle = Vehicle::where('operator_id', Auth::user()->current_organization_id)->find($this->editingVehicleId);
            abort_if($vehicle === null, 404);
            $vehicle->update($attributes);
            \Flux\Flux::toast(text: __('Vehicle updated.'), variant: 'success');
        } else {
            Vehicle::create(['operator_id' => Auth::user()->current_organization_id, ...$attributes]);
            \Flux\Flux::toast(text: __('Vehicle added.'), variant: 'success');
        }

        $this->reset(['editingVehicleId', 'vehicleTypeId', 'plateNo', 'driverName', 'driverPhone', 'transportSupplierId']);
    }

    public function confirmDeleteVehicle(int $vehicleId): void
    {
        $this->deletingKind = 'vehicle';
        $this->confirmDelete($vehicleId, Movement::where('vehicle_id', $vehicleId)->count());
    }

    public function deleteConfirmed(): void
    {
        $operatorId = Auth::user()->current_organization_id;
        $kind = $this->deletingKind;

        $this->guardedDelete(function (int $id) use ($operatorId, $kind): void {
            $model = $kind === 'type'
                ? VehicleType::where('operator_id', $operatorId)->find($id)
                : Vehicle::where('operator_id', $operatorId)->find($id);

            abort_if($model === null, 404);
            $model->delete();
        });
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Vehicles')" :subtitle="__('Vehicle types are yours to define — a config table, not a fixed list.')" />

    {{-- Vehicle types --}}
    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Vehicle Types') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->vehicleTypes as $type)
                    <flux:table.row wire:key="type-{{ $type->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $type->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Capacity')">{{ $type->capacity }}</flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="editType({{ $type->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDeleteType({{ $type->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <x-ui.empty-state icon="truck" :title="__('No vehicle types yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="saveVehicleType" class="flex flex-wrap items-end gap-4 p-6 pt-4">
            <flux:input wire:model="newTypeName" :label="__('Name')" placeholder="Bus - 49 Seater" class="max-w-56" />
            <flux:input type="number" min="1" max="200" wire:model="newTypeCapacity" :label="__('Capacity')" class="max-w-32" />
            <flux:button type="submit" size="sm">{{ $editingTypeId ? __('Save') : __('Add') }}</flux:button>
            @if ($editingTypeId)
                <flux:button size="sm" variant="ghost" wire:click="cancelEditType">{{ __('Cancel') }}</flux:button>
            @endif
        </form>
    </x-ui.card>

    {{-- Fleet --}}
    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Fleet') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Plate') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column minor>{{ __('Driver') }}</flux:table.column>
                <flux:table.column>{{ __('Supplier') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->vehicles as $vehicle)
                    <flux:table.row wire:key="vehicle-{{ $vehicle->id }}">
                        <flux:table.cell class="font-medium" :label="__('Plate')">{{ $vehicle->plate_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('Type')">{{ $vehicle->vehicleType->name }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Driver')">{{ $vehicle->driver_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('Supplier')">{{ $vehicle->transportSupplier?->name ?? __('Own fleet') }}</flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="editVehicle({{ $vehicle->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDeleteVehicle({{ $vehicle->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <x-ui.empty-state icon="truck" :title="__('No vehicles yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="saveVehicle" class="flex flex-wrap items-end gap-4 p-6 pt-4">
            <flux:select wire:model="vehicleTypeId" :label="__('Type')" class="max-w-48">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach ($this->vehicleTypes as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="plateNo" :label="__('Plate number')" class="max-w-40" />
            <flux:input wire:model="driverName" :label="__('Driver name')" class="max-w-48" />
            <flux:input wire:model="driverPhone" :label="__('Driver phone')" class="max-w-40" />
            <flux:select wire:model="transportSupplierId" :label="__('Supplier')" class="max-w-48">
                <flux:select.option value="">{{ __('Own fleet') }}</flux:select.option>
                @foreach ($this->transportSuppliers as $supplier)
                    <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ $editingVehicleId ? __('Save') : __('Add') }}</flux:button>
            @if ($editingVehicleId)
                <flux:button variant="ghost" wire:click="cancelEditVehicle">{{ __('Cancel') }}</flux:button>
            @endif
        </form>
    </x-ui.card>

    <x-ui.confirm-delete-modal :deleting-id="$deletingId" :deleting-dependents="$deletingDependents" :math-a="$mathA" :math-b="$mathB" />
</div>
