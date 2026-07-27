<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Movement;
use App\Models\OperatingOrder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_a_user_with_no_organization_sees_the_empty_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::dashboard')
            ->assertSee(__('You don\'t belong to an organization yet'));
    }

    public function test_a_user_with_no_organization_sees_their_own_onboarding_code(): void
    {
        $user = User::factory()->create(['onboarding_code' => null]);
        $this->actingAs($user);

        $code = $user->ensureOnboardingCode();

        Livewire::test('pages::dashboard')->assertSee($code);
    }

    public function test_a_user_with_no_organization_does_not_see_org_scoped_nav_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertSee(__('Dashboard'));
        $response->assertSee(__('Organizations'));
        $response->assertDontSee(__('Operating Orders'));
        $response->assertDontSee(__('Daily Movements'));
        $response->assertDontSee(__('Groups & Pilgrims'));
    }

    public function test_a_user_with_an_organization_sees_org_scoped_nav_items(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertSee(__('Operating Orders'));
        $response->assertSee(__('Daily Movements'));
        $response->assertSee(__('Groups & Pilgrims'));
    }

    public function test_the_dashboard_only_shows_orders_visible_to_the_current_organization(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();
        $ownOrder = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentA->id]);
        $foreignOrder = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentB->id]);

        $agentUser = User::factory()->create();
        $agentA->addMember($agentUser, OrganizationRole::AgentUser);
        $agentUser->forceFill(['current_organization_id' => $agentA->id])->save();

        $this->actingAs($agentUser);

        Livewire::test('pages::dashboard')
            ->assertSee($ownOrder->reference_no)
            ->assertDontSee($foreignOrder->reference_no);
    }

    public function test_an_upcoming_movement_renders_its_type_on_the_dashboard(): void
    {
        $operator = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id]);
        Movement::factory()->create(['operating_order_id' => $order->id, 'movement_at' => now()->addDay()]);

        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::dashboard')->assertOk();
    }
}
