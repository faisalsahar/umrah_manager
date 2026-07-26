<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Enums\OrganizationRole;
use App\Models\City;
use App\Models\Location;
use App\Models\OperatingOrder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_add_a_city(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')
            ->set('newCityNameAr', 'جدة')
            ->set('newCityNameEn', 'Jeddah')
            ->set('newCityIsPort', true)
            ->call('saveCity')
            ->assertHasNoErrors();

        $city = City::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame('Jeddah', $city->name_en);
        $this->assertTrue($city->is_port);
    }

    public function test_an_operator_user_can_add_a_location_scoped_to_its_own_city(): void
    {
        $operator = Organization::factory()->operator()->create();
        $city = City::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')
            ->set('newLocationNameAr', 'مطار الملك عبدالعزيز')
            ->set('newLocationNameEn', 'King Abdulaziz Airport')
            ->set('newLocationType', LocationType::AirPort->value)
            ->set('newLocationCityId', (string) $city->id)
            ->call('saveLocation')
            ->assertHasNoErrors();

        $location = Location::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame('King Abdulaziz Airport', $location->name_en);
        $this->assertSame($city->id, $location->city_id);
    }

    public function test_a_location_cannot_be_linked_to_another_operators_city(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignCity = City::factory()->create(['operator_id' => $operatorB->id]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')
            ->set('newLocationNameAr', 'اختبار')
            ->set('newLocationNameEn', 'Test')
            ->set('newLocationType', LocationType::AirPort->value)
            ->set('newLocationCityId', (string) $foreignCity->id)
            ->call('saveLocation')
            ->assertHasErrors('newLocationCityId');

        $this->assertSame(0, Location::where('operator_id', $operatorA->id)->count());
    }

    public function test_a_non_operator_cannot_open_the_geography_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')->assertStatus(403);
    }

    public function test_a_city_can_be_edited(): void
    {
        $operator = Organization::factory()->operator()->create();
        $city = City::factory()->create(['operator_id' => $operator->id, 'name_en' => 'Old Name', 'is_port' => false]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')
            ->call('editCity', $city->id)
            ->set('newCityNameEn', 'New Name')
            ->set('newCityIsPort', true)
            ->call('saveCity')
            ->assertHasNoErrors();

        $city->refresh();
        $this->assertSame('New Name', $city->name_en);
        $this->assertTrue($city->is_port);
    }

    public function test_a_city_with_no_dependents_can_be_deleted_without_the_math_check(): void
    {
        $operator = Organization::factory()->operator()->create();
        $city = City::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')
            ->call('confirmDeleteCity', $city->id)
            ->call('deleteConfirmed');

        $this->assertModelMissing($city);
    }

    public function test_deleting_a_city_used_by_an_operating_order_requires_the_correct_math_answer(): void
    {
        $operator = Organization::factory()->operator()->create();
        $city = City::factory()->create(['operator_id' => $operator->id]);
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'arrival_city_id' => $city->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::geography.index')
            ->call('confirmDeleteCity', $city->id)
            ->set('mathAnswer', '-1')
            ->call('deleteConfirmed')
            ->assertHasErrors('mathAnswer');

        $this->assertModelExists($city);

        $correctAnswer = $component->get('mathA') + $component->get('mathB');
        $component->set('mathAnswer', (string) $correctAnswer)->call('deleteConfirmed');

        $this->assertModelMissing($city);
        $this->assertNull($order->fresh()->arrival_city_id);
    }

    public function test_a_location_can_be_edited_and_deleted(): void
    {
        $operator = Organization::factory()->operator()->create();
        $city = City::factory()->create(['operator_id' => $operator->id]);
        $location = Location::factory()->create(['operator_id' => $operator->id, 'city_id' => $city->id, 'name_en' => 'Old Name']);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::geography.index')
            ->call('editLocation', $location->id)
            ->set('newLocationNameEn', 'New Name')
            ->call('saveLocation')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $location->fresh()->name_en);

        Livewire::test('pages::geography.index')
            ->call('confirmDeleteLocation', $location->id)
            ->call('deleteConfirmed');

        $this->assertModelMissing($location);
    }
}
