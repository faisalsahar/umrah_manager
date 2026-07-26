<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Hotel;
use App\Models\HotelStay;
use App\Models\Movement;
use App\Models\Organization;
use App\Models\Pilgrim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyPilgrimsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAgentUser(Organization $agent): User
    {
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_an_agent_can_see_its_own_pilgrims(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        $ownPilgrim = Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'name' => 'Own Pilgrim']);

        $this->actingAsAgentUser($agent);

        Livewire::test('pages::my-pilgrims.index')
            ->assertSee('Own Pilgrim');
    }

    public function test_an_agent_cannot_see_another_agents_pilgrims(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();
        Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentB->id, 'name' => 'Someone Elses Pilgrim']);

        $this->actingAsAgentUser($agentA);

        Livewire::test('pages::my-pilgrims.index')
            ->assertDontSee('Someone Elses Pilgrim');
    }

    public function test_search_filters_by_name_passport_or_nationality(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'name' => 'Ahmed Osman', 'passport_number' => 'X111111']);
        Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'name' => 'Fatima Ali', 'passport_number' => 'Y222222']);

        $this->actingAsAgentUser($agent);

        Livewire::test('pages::my-pilgrims.index')
            ->set('search', 'X111111')
            ->assertSee('Ahmed Osman')
            ->assertDontSee('Fatima Ali');
    }

    public function test_shows_where_a_pilgrim_currently_is(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        $pilgrim = Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'name' => 'Ahmed Osman']);
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id, 'name' => 'Hilton Makkah']);
        HotelStay::factory()->create([
            'pilgrim_id' => $pilgrim->id,
            'hotel_id' => $hotel->id,
            'check_in' => now()->subDay(),
            'check_out' => now()->addDay(),
        ]);

        $this->actingAsAgentUser($agent);

        Livewire::test('pages::my-pilgrims.index')
            ->assertSee('Ahmed Osman')
            ->assertSee('Hilton Makkah');
    }

    public function test_shows_not_yet_travelled_when_no_current_stay_or_movement(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'name' => 'Not Travelled Yet']);

        $this->actingAsAgentUser($agent);

        Livewire::test('pages::my-pilgrims.index')
            ->assertSee('Not Travelled Yet')
            ->assertSee('Not yet travelled');
    }

    public function test_shows_the_next_upcoming_movement(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        $pilgrim = Pilgrim::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'name' => 'Ahmed Osman']);
        $movement = Movement::factory()->create(['movement_at' => now()->addDays(3)]);
        $movement->pilgrims()->attach($pilgrim->id);

        $this->actingAsAgentUser($agent);

        Livewire::test('pages::my-pilgrims.index')
            ->assertSee($movement->movementType->name)
            ->assertSee(now()->addDays(3)->format('Y-m-d'));
    }

    public function test_an_operator_cannot_open_the_my_pilgrims_screen(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::my-pilgrims.index')->assertStatus(403);
    }
}
