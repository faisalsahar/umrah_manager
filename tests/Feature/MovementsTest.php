<?php

namespace Tests\Feature;

use App\Enums\MovementCategory;
use App\Enums\MovementStatus;
use App\Enums\OrganizationRole;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_operator_sees_movements_from_every_order_it_operates(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();
        $orderA = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentA->id]);
        $orderB = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentB->id]);
        Movement::factory()->create(['operating_order_id' => $orderA->id]);
        Movement::factory()->create(['operating_order_id' => $orderB->id]);

        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Supervisor);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movements.index')
            ->assertSee($orderA->reference_no)
            ->assertSee($orderB->reference_no);
    }

    public function test_an_agent_only_sees_movements_on_orders_it_sent_in(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();
        $orderA = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentA->id]);
        $orderB = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentB->id]);
        Movement::factory()->create(['operating_order_id' => $orderA->id]);
        Movement::factory()->create(['operating_order_id' => $orderB->id]);

        $agentUser = User::factory()->create();
        $agentA->addMember($agentUser, OrganizationRole::AgentUser);
        $agentUser->forceFill(['current_organization_id' => $agentA->id])->save();

        $this->actingAs($agentUser);

        Livewire::test('pages::movements.index')
            ->assertSee($orderA->reference_no)
            ->assertDontSee($orderB->reference_no);
    }

    public function test_movements_can_be_filtered_by_type(): void
    {
        $operator = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id]);
        $arrivalType = MovementType::factory()->create(['operator_id' => $operator->id, 'category' => MovementCategory::Arrival]);
        $departureType = MovementType::factory()->create(['operator_id' => $operator->id, 'category' => MovementCategory::Departure]);
        Movement::factory()->create(['operating_order_id' => $order->id, 'movement_type_id' => $arrivalType->id, 'from_location' => 'Arrival Spot']);
        Movement::factory()->create(['operating_order_id' => $order->id, 'movement_type_id' => $departureType->id, 'from_location' => 'Departure Spot']);

        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Supervisor);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movements.index')
            ->set('type', (string) $arrivalType->id)
            ->assertSee('Arrival Spot')
            ->assertDontSee('Departure Spot');
    }

    public function test_movements_can_be_filtered_by_status(): void
    {
        $operator = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id]);
        Movement::factory()->create(['operating_order_id' => $order->id, 'status' => MovementStatus::Completed, 'from_location' => 'Done Leg']);
        Movement::factory()->create(['operating_order_id' => $order->id, 'status' => MovementStatus::Pending, 'from_location' => 'Pending Leg']);

        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Supervisor);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movements.index')
            ->set('status', MovementStatus::Completed->value)
            ->assertSee('Done Leg')
            ->assertDontSee('Pending Leg');
    }

    public function test_a_user_with_no_organization_sees_no_movements(): void
    {
        $order = OperatingOrder::factory()->create();
        Movement::factory()->create(['operating_order_id' => $order->id]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::movements.index')
            ->assertDontSee($order->reference_no);
    }
}
