<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\PermitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PermitTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_add_a_permit_type(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::permit-types.index')
            ->set('newNameAr', 'تصريح نسك للعمرة')
            ->set('newNameEn', 'Nusuk Umrah Permit')
            ->call('savePermitType')
            ->assertHasNoErrors();

        $permitType = PermitType::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame('Nusuk Umrah Permit', $permitType->name_en);
    }

    public function test_a_non_operator_cannot_open_the_permit_types_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::permit-types.index')->assertStatus(403);
    }

    public function test_a_permit_type_can_be_edited_and_deleted(): void
    {
        $operator = Organization::factory()->operator()->create();
        $permitType = PermitType::factory()->create(['operator_id' => $operator->id, 'name_en' => 'Old Name']);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::permit-types.index')
            ->call('edit', $permitType->id)
            ->set('newNameEn', 'New Name')
            ->call('savePermitType')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $permitType->fresh()->name_en);

        Livewire::test('pages::permit-types.index')
            ->call('confirmDelete', $permitType->id, 0)
            ->call('deleteConfirmed');

        $this->assertModelMissing($permitType);
    }
}
