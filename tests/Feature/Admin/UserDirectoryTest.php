<?php

namespace Tests\Feature\Admin;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('app.admin_email', 'info@computerize.biz')]);
    }

    public function test_a_non_admin_cannot_open_the_user_directory(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::admin.users.index')->assertStatus(403);
    }

    public function test_the_admin_sees_every_user_and_their_organization(): void
    {
        $operator = Organization::factory()->operator()->create(['name_en' => 'Al-Rahma']);
        $member = User::factory()->create(['name' => 'Nadia Al-Amin']);
        $operator->addMember($member, OrganizationRole::Admin);

        $orgless = User::factory()->create(['name' => 'Orphan User']);

        $this->actingAs($this->admin());

        Livewire::test('pages::admin.users.index')
            ->assertSee('Nadia Al-Amin')
            ->assertSee('Al-Rahma')
            ->assertSee('Orphan User')
            ->assertSee(__('No organization yet'));
    }

    public function test_the_admin_does_not_see_themselves_in_the_directory(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test('pages::admin.users.index')
            ->assertDontSee($admin->email);
    }

    public function test_a_sub_agents_parent_is_shown(): void
    {
        $parentAgent = Organization::factory()->agent()->create(['name_en' => 'Sudan Travel Agency']);
        $subAgent = Organization::factory()->agent()->create([
            'name_en' => 'Khartoum Sub-Agent',
            'parent_organization_id' => $parentAgent->id,
        ]);
        $member = User::factory()->create(['name' => 'Sub Agent Staffer']);
        $subAgent->addMember($member, OrganizationRole::AgentUser);

        $this->actingAs($this->admin());

        Livewire::test('pages::admin.users.index')
            ->assertSee('Sub Agent Staffer')
            ->assertSee('Khartoum Sub-Agent')
            ->assertSee('Sudan Travel Agency');
    }

    public function test_search_filters_by_name_email_or_onboarding_code(): void
    {
        User::factory()->create(['name' => 'Ahmed Osman', 'email' => 'ahmed@example.test']);
        User::factory()->create(['name' => 'Fatima Ali', 'email' => 'fatima@example.test']);

        $this->actingAs($this->admin());

        Livewire::test('pages::admin.users.index')
            ->set('search', 'Ahmed')
            ->assertSee('Ahmed Osman')
            ->assertDontSee('Fatima Ali');
    }
}
