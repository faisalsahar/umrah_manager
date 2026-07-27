<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\City;
use App\Models\Hotel;
use App\Models\MovementType;
use App\Models\Organization;
use App\Models\PermitType;
use App\Models\Service;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UmrahCompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('app.admin_email', 'info@computerize.biz')]);
    }

    public function test_a_non_admin_cannot_open_the_umrah_companies_screen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::admin.umrah-companies.index')->assertStatus(403);
    }

    public function test_the_admin_can_create_a_company_and_hand_it_to_its_founder(): void
    {
        $founder = User::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.index')
            ->set('newNameAr', 'شركة الرحمة لخدمات العمرة')
            ->set('newNameEn', 'Al-Rahma Umrah Services')
            ->set('newCountry', 'SA')
            ->set('newLicenseNo', 'MISA-0001')
            ->set('founderCode', $founder->onboarding_code)
            ->call('createCompany')
            ->assertHasNoErrors();

        $company = Organization::where('name_en', 'Al-Rahma Umrah Services')->firstOrFail();
        $this->assertSame(OrganizationType::Operator, $company->type);
        $this->assertSame(OrganizationRole::Owner, $founder->fresh()->roleIn($company));
    }

    public function test_creating_a_company_seeds_its_starter_catalog(): void
    {
        $founder = User::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.index')
            ->set('newNameAr', 'شركة الرحمة لخدمات العمرة')
            ->set('newNameEn', 'Al-Rahma Umrah Services')
            ->set('founderCode', $founder->onboarding_code)
            ->call('createCompany');

        $company = Organization::where('name_en', 'Al-Rahma Umrah Services')->firstOrFail();

        $this->assertGreaterThan(0, VehicleType::where('operator_id', $company->id)->count());
        $this->assertGreaterThan(0, City::where('operator_id', $company->id)->count());
        $this->assertGreaterThan(0, MovementType::where('operator_id', $company->id)->count());
        $this->assertGreaterThan(0, PermitType::where('operator_id', $company->id)->count());
        $this->assertGreaterThan(0, Service::where('operator_id', $company->id)->count());
        $this->assertGreaterThan(0, Hotel::where('operator_id', $company->id)->count());
    }

    public function test_creating_a_company_fails_with_an_unknown_onboarding_code(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.index')
            ->set('newNameAr', 'اختبار')
            ->set('newNameEn', 'Test Co')
            ->set('founderCode', 'NOTREAL1')
            ->call('createCompany')
            ->assertHasErrors('founderCode');

        $this->assertSame(0, Organization::where('name_en', 'Test Co')->count());
    }

    public function test_the_admin_sees_every_umrah_company(): void
    {
        $companyA = Organization::factory()->operator()->create();
        $companyB = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();

        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.index')
            ->assertSee($companyA->name)
            ->assertSee($companyB->name)
            ->assertDontSee($agent->name);
    }

    public function test_the_admin_can_view_a_companys_roster_and_set_its_subscription(): void
    {
        $company = Organization::factory()->operator()->create();
        $owner = User::factory()->create(['name' => 'Company Owner']);
        $company->addMember($owner, OrganizationRole::Owner);

        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.show', ['umrahCompany' => $company])
            ->assertSee('Company Owner')
            ->set('subscriptionExpiresAt', now()->addYear()->format('Y-m-d'))
            ->set('isActive', false)
            ->call('updateSubscription')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertFalse($company->is_active);
        $this->assertTrue($company->subscription_expires_at->isFuture());
    }

    public function test_the_admin_can_add_an_existing_user_to_a_company_by_onboarding_code(): void
    {
        $company = Organization::factory()->operator()->create();
        $newMember = User::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.show', ['umrahCompany' => $company])
            ->set('memberCode', $newMember->onboarding_code)
            ->set('memberRole', OrganizationRole::DataEntry->value)
            ->call('addMemberByCode')
            ->assertHasNoErrors();

        $this->assertSame(OrganizationRole::DataEntry, $newMember->fresh()->roleIn($company));
    }

    public function test_adding_a_member_with_an_unknown_code_fails_gracefully(): void
    {
        $company = Organization::factory()->operator()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.show', ['umrahCompany' => $company])
            ->set('memberCode', 'NOTREAL1')
            ->set('memberRole', OrganizationRole::DataEntry->value)
            ->call('addMemberByCode')
            ->assertHasErrors('memberCode');
    }

    public function test_a_non_admin_cannot_view_a_companys_roster(): void
    {
        $company = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::admin.umrah-companies.show', ['umrahCompany' => $company])->assertStatus(403);
    }

    public function test_an_agent_organization_is_not_manageable_from_the_umrah_company_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.umrah-companies.show', ['umrahCompany' => $agent])->assertStatus(404);
    }

    public function test_organization_is_subscription_expired_reflects_the_renewal_date(): void
    {
        $expired = Organization::factory()->operator()->create(['subscription_expires_at' => now()->subDay()]);
        $active = Organization::factory()->operator()->create(['subscription_expires_at' => now()->addDay()]);
        $unset = Organization::factory()->operator()->create(['subscription_expires_at' => null]);

        $this->assertTrue($expired->isSubscriptionExpired());
        $this->assertFalse($active->isSubscriptionExpired());
        $this->assertFalse($unset->isSubscriptionExpired());
    }
}
