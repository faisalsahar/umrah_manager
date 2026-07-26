<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_define_a_vehicle_type(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::vehicles.index')
            ->set('newTypeName', 'Bus - 49 Seater')
            ->set('newTypeCapacity', '49')
            ->call('saveVehicleType')
            ->assertHasNoErrors();

        $type = VehicleType::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame(49, $type->capacity);
    }

    public function test_a_vehicle_can_be_added_to_the_own_fleet_or_a_supplier(): void
    {
        $operator = Organization::factory()->operator()->create();
        $supplier = Organization::factory()->transportSupplier()->create();
        $vehicleType = VehicleType::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::vehicles.index')
            ->set('vehicleTypeId', (string) $vehicleType->id)
            ->set('plateNo', 'ABC 1234')
            ->call('saveVehicle')
            ->assertHasNoErrors();

        $ownVehicle = Vehicle::where('plate_no', 'ABC 1234')->firstOrFail();
        $this->assertNull($ownVehicle->transport_supplier_id);

        Livewire::test('pages::vehicles.index')
            ->set('vehicleTypeId', (string) $vehicleType->id)
            ->set('plateNo', 'XYZ 5678')
            ->set('transportSupplierId', (string) $supplier->id)
            ->call('saveVehicle')
            ->assertHasNoErrors();

        $supplierVehicle = Vehicle::where('plate_no', 'XYZ 5678')->firstOrFail();
        $this->assertSame($supplier->id, $supplierVehicle->transport_supplier_id);
    }

    public function test_a_non_operator_cannot_open_the_vehicles_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::vehicles.index')->assertStatus(403);
    }

    public function test_a_vehicle_type_with_no_vehicles_can_be_deleted_without_the_math_check(): void
    {
        $operator = Organization::factory()->operator()->create();
        $type = VehicleType::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::vehicles.index')
            ->call('confirmDeleteType', $type->id)
            ->call('deleteConfirmed');

        $this->assertModelMissing($type);
    }

    public function test_deleting_a_vehicle_type_with_vehicles_requires_the_correct_math_answer(): void
    {
        $operator = Organization::factory()->operator()->create();
        $type = VehicleType::factory()->create(['operator_id' => $operator->id]);
        $vehicle = Vehicle::factory()->create(['operator_id' => $operator->id, 'vehicle_type_id' => $type->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::vehicles.index')
            ->call('confirmDeleteType', $type->id)
            ->set('mathAnswer', '-1')
            ->call('deleteConfirmed')
            ->assertHasErrors('mathAnswer');

        $this->assertModelExists($type);
        $this->assertModelExists($vehicle);

        $correctAnswer = $component->get('mathA') + $component->get('mathB');
        $component->set('mathAnswer', (string) $correctAnswer)->call('deleteConfirmed');

        $this->assertModelMissing($type);
    }

    public function test_a_vehicle_can_be_edited_and_deleted(): void
    {
        $operator = Organization::factory()->operator()->create();
        $type = VehicleType::factory()->create(['operator_id' => $operator->id]);
        $vehicle = Vehicle::factory()->create(['operator_id' => $operator->id, 'vehicle_type_id' => $type->id, 'plate_no' => 'OLD 000']);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::vehicles.index')
            ->call('editVehicle', $vehicle->id)
            ->set('plateNo', 'NEW 111')
            ->call('saveVehicle')
            ->assertHasNoErrors();

        $this->assertSame('NEW 111', $vehicle->fresh()->plate_no);

        Livewire::test('pages::vehicles.index')
            ->call('confirmDeleteVehicle', $vehicle->id)
            ->call('deleteConfirmed');

        $this->assertModelMissing($vehicle);
    }
}
