<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\HotelContractType;
use App\Enums\MovementSource;
use App\Enums\OrganizationRole;
use App\Models\City;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use App\Models\Organization;
use App\Models\Pilgrim;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every foreign-key input on a write action must be scoped to the acting
 * organization — otherwise a tampered Livewire request can link one operator's
 * order/group/vehicle to another operator's data and leak it back onto the first
 * operator's own screen (names, passport numbers, driver phone numbers, …).
 *
 * These tests attempt exactly that cross-link and assert it is rejected.
 */
class CrossTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operating_order_cannot_be_linked_to_another_operators_group(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignGroup = Group::factory()->create(['operator_id' => $operatorB->id]);
        Pilgrim::factory()->create(['operator_id' => $operatorB->id, 'group_id' => $foreignGroup->id, 'name' => 'Secret Pilgrim']);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::DataEntry);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.create')
            ->set('groupId', (string) $foreignGroup->id)
            ->call('save')
            ->assertHasErrors('groupId');

        $this->assertSame(0, OperatingOrder::where('group_id', $foreignGroup->id)->count());
    }

    public function test_an_operating_order_cannot_be_linked_to_a_non_agent_organization(): void
    {
        $operator = Organization::factory()->operator()->create();
        $anotherOperator = Organization::factory()->operator()->create(); // not an agent
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::DataEntry);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.create')
            ->set('agentId', (string) $anotherOperator->id)
            ->call('save')
            ->assertHasErrors('agentId');
    }

    public function test_a_movement_cannot_be_assigned_another_operators_vehicle(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operatorA->id]);
        $movementType = MovementType::factory()->create(['operator_id' => $operatorA->id]);
        $foreignVehicleType = VehicleType::factory()->create(['operator_id' => $operatorB->id]);
        $foreignVehicle = Vehicle::factory()->create([
            'operator_id' => $operatorB->id,
            'vehicle_type_id' => $foreignVehicleType->id,
            'driver_phone' => '+966500000000',
        ]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::Supervisor);

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.show', ['operatingOrder' => $order])
            ->set('movementTypeId', (string) $movementType->id)
            ->set('source', MovementSource::OperatorProvided->value)
            ->set('vehicleId', (string) $foreignVehicle->id)
            ->set('pilgrimCount', 5)
            ->call('addMovement')
            ->assertHasErrors('vehicleId');

        $this->assertSame(0, $order->movements()->count());
    }

    public function test_an_operating_order_cannot_be_linked_to_another_operators_city(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignCity = City::factory()->create(['operator_id' => $operatorB->id]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::DataEntry);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.create')
            ->set('arrivalCityId', (string) $foreignCity->id)
            ->call('save')
            ->assertHasErrors('arrivalCityId');

        $this->assertSame(0, OperatingOrder::where('arrival_city_id', $foreignCity->id)->count());
    }

    public function test_a_pilgrims_mahram_link_cannot_point_outside_the_group(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operatorA->id]);
        $foreignPilgrim = Pilgrim::factory()->create(['operator_id' => $operatorB->id, 'name' => 'Foreign Pilgrim']);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::DataEntry);

        $this->actingAs($user);

        Livewire::test('pages::groups.show', ['group' => $group])
            ->set('name', 'New Pilgrim')
            ->set('passportNumber', 'X1234567')
            ->set('gender', Gender::Male->value)
            ->set('mahramOfPilgrimId', (string) $foreignPilgrim->id)
            ->call('addPilgrim')
            ->assertHasErrors('mahramOfPilgrimId');

        $this->assertSame(0, Pilgrim::where('name', 'New Pilgrim')->count());
    }

    public function test_a_hotel_contract_cannot_reference_another_hotels_room_type(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotelA = Hotel::factory()->create(['operator_id' => $operator->id]);
        $hotelB = Hotel::factory()->create(['operator_id' => $operator->id]);
        $foreignRoomType = RoomType::factory()->create(['hotel_id' => $hotelB->id]);

        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);

        $this->actingAs($user);

        Livewire::test('pages::hotels.show', ['hotel' => $hotelA])
            ->set('contractRoomTypeId', (string) $foreignRoomType->id)
            ->set('contractType', HotelContractType::Allotment->value)
            ->set('contractStartsOn', now()->addWeek()->format('Y-m-d'))
            ->set('contractEndsOn', now()->addMonth()->format('Y-m-d'))
            ->set('ratePurchase', '90')
            ->set('rateSale', '160')
            ->call('addContract')
            ->assertHasErrors('contractRoomTypeId');

        $this->assertSame(0, $hotelA->fresh()->contracts()->count());
    }

    public function test_a_vehicle_cannot_be_added_with_another_operators_vehicle_type(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignType = VehicleType::factory()->create(['operator_id' => $operatorB->id]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::vehicles.index')
            ->set('vehicleTypeId', (string) $foreignType->id)
            ->set('plateNo', 'FOR 1234')
            ->call('saveVehicle')
            ->assertHasErrors('vehicleTypeId');

        $this->assertSame(0, Vehicle::where('plate_no', 'FOR 1234')->count());
    }
}
