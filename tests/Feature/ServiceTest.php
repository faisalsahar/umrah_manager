<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\ServiceType;
use App\Models\Organization;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_add_a_service(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')
            ->set('newName', 'Ziyarat Tour')
            ->set('newType', ServiceType::Ziyarat->value)
            ->set('newPrice', '75.50')
            ->call('saveService')
            ->assertHasNoErrors();

        $service = Service::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame('Ziyarat Tour', $service->name);
        $this->assertSame(7550, $service->default_sale_price_cents);
        $this->assertTrue($service->is_active);
    }

    public function test_a_service_can_be_deactivated_and_reactivated(): void
    {
        $operator = Organization::factory()->operator()->create();
        $service = Service::factory()->create(['operator_id' => $operator->id, 'is_active' => true]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')->call('toggleActive', $service->id);
        $this->assertFalse($service->fresh()->is_active);

        Livewire::test('pages::services.index')->call('toggleActive', $service->id);
        $this->assertTrue($service->fresh()->is_active);
    }

    public function test_a_user_cannot_toggle_another_operators_service(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignService = Service::factory()->create(['operator_id' => $operatorB->id, 'is_active' => true]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')->call('toggleActive', $foreignService->id)->assertStatus(404);

        $this->assertTrue($foreignService->fresh()->is_active);
    }

    public function test_a_non_operator_cannot_open_the_services_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')->assertStatus(403);
    }

    public function test_a_service_can_be_edited(): void
    {
        $operator = Organization::factory()->operator()->create();
        $service = Service::factory()->create(['operator_id' => $operator->id, 'name' => 'Old Name']);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')
            ->call('edit', $service->id)
            ->set('newName', 'New Name')
            ->call('saveService')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $service->fresh()->name);
    }

    public function test_a_service_with_no_order_lines_can_be_deleted_without_the_math_check(): void
    {
        $operator = Organization::factory()->operator()->create();
        $service = Service::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')
            ->call('confirmDelete', $service->id, 0)
            ->call('deleteConfirmed');

        $this->assertModelMissing($service);
    }

    public function test_delete_confirmed_without_a_prior_confirm_delete_is_a_safe_no_op(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::services.index')->call('deleteConfirmed')->assertStatus(200);
    }

    public function test_a_service_with_order_lines_requires_the_correct_math_answer_to_delete(): void
    {
        $operator = Organization::factory()->operator()->create();
        $service = Service::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::services.index')
            ->call('confirmDelete', $service->id, 1)
            ->set('mathAnswer', '-1')
            ->call('deleteConfirmed')
            ->assertHasErrors('mathAnswer');

        $this->assertModelExists($service);

        $correctAnswer = $component->get('mathA') + $component->get('mathB');
        $component->set('mathAnswer', (string) $correctAnswer)->call('deleteConfirmed');

        $this->assertModelMissing($service);
    }
}
