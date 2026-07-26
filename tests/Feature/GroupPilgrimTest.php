<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrganizationRole;
use App\Models\Group;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupPilgrimTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_create_a_group(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::DataEntry);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::groups.index')
            ->set('newName', 'Group SD-2026-099')
            ->set('newArrivalDate', now()->addWeek()->format('Y-m-d'))
            ->set('newDepartureDate', now()->addWeeks(2)->format('Y-m-d'))
            ->call('createGroup')
            ->assertHasNoErrors();

        $this->assertSame(1, Group::where('operator_id', $operator->id)->count());
    }

    public function test_a_non_operator_cannot_open_the_groups_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::groups.index')->assertStatus(403);
    }

    public function test_a_pilgrim_can_be_added_to_a_group_with_a_mahram_link(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $husband = $group->pilgrims()->create([
            'operator_id' => $operator->id,
            'name' => 'Ahmed Osman',
            'passport_number' => 'A1234567',
            'gender' => Gender::Male,
        ]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::DataEntry);

        $this->actingAs($user);

        Livewire::test('pages::groups.show', ['group' => $group])
            ->set('name', 'Fatima Osman')
            ->set('passportNumber', 'B7654321')
            ->set('gender', Gender::Female->value)
            ->set('mahramOfPilgrimId', (string) $husband->id)
            ->call('addPilgrim')
            ->assertHasNoErrors();

        $wife = $group->pilgrims()->where('name', 'Fatima Osman')->firstOrFail();
        $this->assertSame($husband->id, $wife->mahram_of_pilgrim_id);
    }

    public function test_a_stranger_cannot_view_a_groups_pilgrims(): void
    {
        $group = Group::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger);

        Livewire::test('pages::groups.show', ['group' => $group])->assertStatus(403);
    }
}
