<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_bring_in_a_new_organization_and_the_code_holder_becomes_its_owner(): void
    {
        $manager = User::factory()->create();
        $operator = Organization::factory()->operator()->create();
        $operator->addMember($manager, OrganizationRole::Owner);
        $manager->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($manager);

        $newOwner = User::factory()->create();

        Livewire::test('pages::organizations.index')
            ->set('newMemberCode', $newOwner->onboarding_code)
            ->set('newNameAr', 'وكالة السودان للسفر')
            ->set('newNameEn', 'Sudan Travel Agency')
            ->set('newType', OrganizationType::Agent->value)
            ->set('newCountry', 'SA')
            ->call('bringInOrganization')
            ->assertHasNoErrors();

        $organization = Organization::where('name_en', 'Sudan Travel Agency')->firstOrFail();

        $this->assertSame(OrganizationRole::Owner, $newOwner->fresh()->roleIn($organization));
        // The manager who brought them in never becomes a member themselves.
        $this->assertNull($manager->fresh()->roleIn($organization));
    }

    public function test_a_user_who_manages_no_organization_cannot_bring_in_a_new_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $newOwner = User::factory()->create();

        Livewire::test('pages::organizations.index')
            ->set('newMemberCode', $newOwner->onboarding_code)
            ->set('newNameAr', 'وكالة السودان للسفر')
            ->set('newNameEn', 'Sudan Travel Agency')
            ->set('newType', OrganizationType::Agent->value)
            ->call('bringInOrganization')
            ->assertStatus(403);

        $this->assertSame(0, Organization::where('name_en', 'Sudan Travel Agency')->count());
    }

    public function test_bringing_in_an_organization_with_an_unknown_code_fails_gracefully(): void
    {
        $manager = User::factory()->create();
        $operator = Organization::factory()->operator()->create();
        $operator->addMember($manager, OrganizationRole::Owner);
        $manager->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($manager);

        Livewire::test('pages::organizations.index')
            ->set('newMemberCode', 'NOSUCHCODE')
            ->set('newNameAr', 'وكالة السودان للسفر')
            ->set('newNameEn', 'Sudan Travel Agency')
            ->set('newType', OrganizationType::Agent->value)
            ->call('bringInOrganization')
            ->assertHasErrors('newMemberCode');

        $this->assertSame(0, Organization::where('name_en', 'Sudan Travel Agency')->count());
    }

    public function test_a_manager_cannot_bring_in_an_umrah_company(): void
    {
        $manager = User::factory()->create();
        $operator = Organization::factory()->operator()->create();
        $operator->addMember($manager, OrganizationRole::Owner);
        $manager->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($manager);

        $newOwner = User::factory()->create();

        Livewire::test('pages::organizations.index')
            ->set('newMemberCode', $newOwner->onboarding_code)
            ->set('newNameAr', 'شركة الرحمة لخدمات العمرة')
            ->set('newNameEn', 'Al-Rahma Umrah Services')
            ->set('newType', OrganizationType::Operator->value)
            ->call('bringInOrganization')
            ->assertHasErrors('newType');

        $this->assertSame(0, Organization::where('name_en', 'Al-Rahma Umrah Services')->count());
    }

    public function test_owner_can_add_a_member_by_onboarding_code(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $organization = Organization::factory()->operator()->create();
        $organization->addMember($owner, OrganizationRole::Owner);
        $owner->forceFill(['current_organization_id' => $organization->id])->save();

        $this->actingAs($owner);

        Livewire::test('pages::organizations.index')
            ->set('memberCode', $newMember->onboarding_code)
            ->set('memberRole', OrganizationRole::DataEntry->value)
            ->call('addMemberByCode')
            ->assertHasNoErrors();

        $this->assertSame(OrganizationRole::DataEntry, $newMember->fresh()->roleIn($organization));
    }

    public function test_a_data_entry_user_cannot_manage_organization_members(): void
    {
        $entryUser = User::factory()->create();
        $organization = Organization::factory()->operator()->create();
        $organization->addMember($entryUser, OrganizationRole::DataEntry);

        $this->assertFalse($entryUser->can('manageMembers', $organization));
    }

    public function test_an_owner_can_manage_organization_members(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->operator()->create();
        $organization->addMember($owner, OrganizationRole::Owner);

        $this->assertTrue($owner->can('manageMembers', $organization));
    }

    public function test_switching_organizations_updates_current_organization(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::factory()->operator()->create();
        $orgB = Organization::factory()->agent()->create();
        $orgA->addMember($user, OrganizationRole::Owner);
        $orgB->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $orgA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::organizations.index')->call('switchTo', $orgB->id);

        $this->assertSame($orgB->id, $user->fresh()->current_organization_id);
    }

    public function test_a_user_cannot_switch_to_an_organization_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $foreignOrg = Organization::factory()->operator()->create();

        $this->actingAs($user);

        Livewire::test('pages::organizations.index')->call('switchTo', $foreignOrg->id);

        $this->assertNull($user->fresh()->current_organization_id);
    }

    public function test_ensure_current_organization_defaults_to_first_membership(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->operator()->create();
        $organization->addMember($user, OrganizationRole::Owner);

        $this->assertNull($user->current_organization_id);

        $result = $user->ensureCurrentOrganization();

        $this->assertSame($organization->id, $result->id);
        $this->assertSame($organization->id, $user->fresh()->current_organization_id);
    }

    public function test_login_sets_current_organization_automatically(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->operator()->create();
        $organization->addMember($user, OrganizationRole::Owner);

        event(new Login('web', $user, false));

        $this->assertSame($organization->id, $user->fresh()->current_organization_id);
    }

    public function test_balance_with_reflects_charges_and_payments(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();

        $operator->ledgerEntries()->create([
            'counterparty_organization_id' => $agent->id,
            'entry_type' => LedgerEntryType::Charge,
            'amount_cents' => 100000,
            'occurred_on' => now(),
        ]);
        $operator->ledgerEntries()->create([
            'counterparty_organization_id' => $agent->id,
            'entry_type' => LedgerEntryType::Payment,
            'amount_cents' => 40000,
            'occurred_on' => now(),
        ]);

        $this->assertSame(60000, $operator->balanceWith($agent));
    }

    public function test_a_manager_can_bring_in_a_sub_agent_under_an_existing_agent(): void
    {
        $parentAgent = Organization::factory()->agent()->create();
        $manager = User::factory()->create();
        $operator = Organization::factory()->operator()->create();
        $operator->addMember($manager, OrganizationRole::Owner);
        $manager->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($manager);

        $newOwner = User::factory()->create();

        Livewire::test('pages::organizations.index')
            ->set('newMemberCode', $newOwner->onboarding_code)
            ->set('newNameAr', 'وكيل الخرطوم الفرعي')
            ->set('newNameEn', 'Khartoum Sub-Agent')
            ->set('newType', OrganizationType::Agent->value)
            ->set('newParentOrganizationId', (string) $parentAgent->id)
            ->call('bringInOrganization')
            ->assertHasNoErrors();

        $subAgent = Organization::where('name_en', 'Khartoum Sub-Agent')->firstOrFail();
        $this->assertTrue($subAgent->isSubAgentOf($parentAgent));
        $this->assertTrue($parentAgent->subAgents->contains($subAgent));
        $this->assertSame(OrganizationRole::Owner, $newOwner->fresh()->roleIn($subAgent));
    }

    public function test_a_new_hotel_organization_cannot_be_nested_under_an_agent(): void
    {
        $parentAgent = Organization::factory()->agent()->create();
        $manager = User::factory()->create();
        $operator = Organization::factory()->operator()->create();
        $operator->addMember($manager, OrganizationRole::Owner);
        $manager->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($manager);

        $newOwner = User::factory()->create();

        Livewire::test('pages::organizations.index')
            ->set('newMemberCode', $newOwner->onboarding_code)
            ->set('newNameAr', 'اختبار')
            ->set('newNameEn', 'Test Hotel Co')
            ->set('newType', OrganizationType::Hotel->value)
            ->set('newParentOrganizationId', (string) $parentAgent->id)
            ->call('bringInOrganization')
            ->assertHasNoErrors();

        $hotelOrg = Organization::where('name_en', 'Test Hotel Co')->firstOrFail();
        $this->assertNull($hotelOrg->parent_organization_id);
    }
}
