<?php

namespace Tests\Feature;

use App\Enums\MovementSource;
use App\Enums\OperatingOrderStatus;
use App\Enums\OrderLineType;
use App\Enums\OrganizationRole;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperatingOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_sees_every_order_it_operates(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();

        OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentA->id]);
        OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentB->id]);

        $this->assertSame(2, OperatingOrder::visibleTo($operator)->count());
    }

    public function test_an_agent_sees_only_the_orders_it_sent_in(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();

        OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentA->id]);
        OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agentB->id]);

        $this->assertSame(1, OperatingOrder::visibleTo($agentA)->count());
    }

    public function test_a_stranger_organization_sees_nothing(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        $stranger = Organization::factory()->agent()->create();

        OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id]);

        $this->assertSame(0, OperatingOrder::visibleTo($stranger)->count());
    }

    public function test_reference_number_is_generated_and_unique(): void
    {
        $orderA = OperatingOrder::factory()->create();
        $orderB = OperatingOrder::factory()->create();

        $this->assertNotEmpty($orderA->reference_no);
        $this->assertNotSame($orderA->reference_no, $orderB->reference_no);
    }

    public function test_an_operator_user_can_create_an_operating_order(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::DataEntry);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.create')
            ->set('agentId', (string) $agent->id)
            ->set('arrivalDate', now()->addWeek()->format('Y-m-d'))
            ->set('arrivalFlightNo', 'SV 799')
            ->call('save')
            ->assertHasNoErrors();

        $order = OperatingOrder::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame($operator->id, $order->operator_id);
        $this->assertSame($user->id, $order->entered_by_user_id);
        $this->assertSame('SV 799', $order->arrival_flight_no);
    }

    public function test_an_agent_only_user_cannot_open_the_create_order_screen(): void
    {
        $agent = Organization::factory()->agent()->create();
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.create')->assertStatus(403);
    }

    public function test_the_operator_can_approve_a_pending_order(): void
    {
        $operator = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.show', ['operatingOrder' => $order])
            ->call('approve');

        $this->assertSame(OperatingOrderStatus::Approved, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->approved_at);
    }

    public function test_an_agent_cannot_approve_an_order_it_only_sent_in(): void
    {
        $operator = Organization::factory()->operator()->create();
        $agent = Organization::factory()->agent()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id]);
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.show', ['operatingOrder' => $order])
            ->call('approve');

        $this->assertSame(OperatingOrderStatus::Draft, $order->fresh()->status);
    }

    public function test_a_stranger_cannot_view_an_order(): void
    {
        $order = OperatingOrder::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger);

        Livewire::test('pages::operating-orders.show', ['operatingOrder' => $order])
            ->assertStatus(403);
    }

    public function test_movements_can_be_added_to_an_order(): void
    {
        $operator = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id]);
        $movementType = MovementType::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Supervisor);

        $this->actingAs($user);

        Livewire::test('pages::operating-orders.show', ['operatingOrder' => $order])
            ->set('movementTypeId', (string) $movementType->id)
            ->set('source', MovementSource::OperatorProvided->value)
            ->set('fromLocation', 'Jeddah Airport')
            ->set('toLocation', 'Makkah Hotel')
            ->set('pilgrimCount', 12)
            ->call('addMovement')
            ->assertHasNoErrors();

        $this->assertSame(1, $order->movements()->count());
        $this->assertSame('Jeddah Airport', $order->movements()->first()->from_location);
    }

    public function test_margin_is_sale_minus_purchase_across_all_lines(): void
    {
        $order = OperatingOrder::factory()->create();
        $order->orderLines()->create([
            'line_type' => OrderLineType::Service,
            'quantity' => 2,
            'purchase_price_cents' => 1000,
            'sale_price_cents' => 1500,
        ]);
        $order->orderLines()->create([
            'line_type' => OrderLineType::Service,
            'quantity' => 1,
            'purchase_price_cents' => 2000,
            'sale_price_cents' => 2500,
        ]);

        $order->load('orderLines');

        $this->assertSame(4000, $order->totalPurchaseCents()); // 2*1000 + 1*2000
        $this->assertSame(5500, $order->totalSaleCents());     // 2*1500 + 1*2500
        $this->assertSame(1500, $order->marginCents());
    }
}
