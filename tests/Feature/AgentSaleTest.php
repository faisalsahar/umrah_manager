<?php

namespace Tests\Feature;

use App\Enums\AgentSalePaymentDirection;
use App\Enums\AgentSaleStatus;
use App\Enums\AgentSaleType;
use App\Enums\OrganizationRole;
use App\Models\AgentSale;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentSaleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAgentOwner(Organization $agent): User
    {
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::Owner);
        $user->forceFill(['current_organization_id' => $agent->id])->save();
        $this->actingAs($user);

        return $user;
    }

    private function actingAsAgentUser(Organization $agent): User
    {
        $user = User::factory()->create();
        $agent->addMember($user, OrganizationRole::AgentUser);
        $user->forceFill(['current_organization_id' => $agent->id])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_an_operator_cannot_open_the_sales_screen(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($user);

        Livewire::test('pages::sales.index')->assertStatus(403);
    }

    public function test_an_agent_user_can_record_a_sale(): void
    {
        $agent = Organization::factory()->agent()->create();
        $this->actingAsAgentUser($agent);

        Livewire::test('pages::sales.index')
            ->set('newSaleType', AgentSaleType::Ticket->value)
            ->set('newCustomerName', 'Ahmed Osman')
            ->set('newDescription', 'Riyadh - Jeddah return')
            ->set('newSupplierReference', 'PNR-ABC123')
            ->set('newSaleDate', now()->format('Y-m-d'))
            ->set('newPurchasePrice', '400')
            ->set('newSalePrice', '550')
            ->call('createSale')
            ->assertHasNoErrors();

        $sale = AgentSale::where('customer_name', 'Ahmed Osman')->firstOrFail();
        $this->assertSame($agent->id, $sale->agent_organization_id);
        $this->assertSame(40000, $sale->purchase_price_cents);
        $this->assertSame(55000, $sale->sale_price_cents);
        $this->assertSame(15000, $sale->marginCents());
        $this->assertNotEmpty($sale->reference_no);
        $this->assertSame(AgentSaleStatus::Issued, $sale->status);
    }

    public function test_a_sale_from_another_agent_is_not_visible(): void
    {
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();
        AgentSale::factory()->create(['agent_organization_id' => $agentB->id, 'customer_name' => 'Someone Elses Customer']);
        AgentSale::factory()->create(['agent_organization_id' => $agentA->id, 'customer_name' => 'Own Customer']);

        $this->actingAsAgentUser($agentA);

        Livewire::test('pages::sales.index')
            ->assertSee('Own Customer')
            ->assertDontSee('Someone Elses Customer');
    }

    public function test_totals_exclude_voided_sales(): void
    {
        $agent = Organization::factory()->agent()->create();
        AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'purchase_price_cents' => 40000,
            'sale_price_cents' => 55000,
            'status' => AgentSaleStatus::Issued,
        ]);
        AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'purchase_price_cents' => 10000,
            'sale_price_cents' => 20000,
            'status' => AgentSaleStatus::Voided,
        ]);

        $this->actingAsAgentUser($agent);

        $totals = Livewire::test('pages::sales.index')->get('totals');

        $this->assertSame(55000, $totals['sale_cents']);
        $this->assertSame(40000, $totals['cost_cents']);
        $this->assertSame(15000, $totals['margin_cents']);
    }

    public function test_recording_a_customer_payment_updates_outstanding_balance(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'purchase_price_cents' => 40000,
            'sale_price_cents' => 55000,
        ]);
        $this->actingAsAgentUser($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->set('customerPaymentAmount', '200')
            ->set('customerPaymentDate', now()->format('Y-m-d'))
            ->call('recordCustomerPayment')
            ->assertHasNoErrors();

        $sale->refresh();
        $this->assertSame(20000, $sale->amountReceivedCents());
        $this->assertSame(35000, $sale->customerOutstandingCents());
    }

    public function test_an_agent_user_cannot_record_a_supplier_payment(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create(['agent_organization_id' => $agent->id]);
        $this->actingAsAgentUser($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->set('supplierPaymentAmount', '100')
            ->set('supplierPaymentDate', now()->format('Y-m-d'))
            ->call('recordSupplierPayment')
            ->assertStatus(403);
    }

    public function test_an_owner_can_record_a_supplier_payment(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'purchase_price_cents' => 40000,
        ]);
        $this->actingAsAgentOwner($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->set('supplierPaymentAmount', '400')
            ->set('supplierPaymentDate', now()->format('Y-m-d'))
            ->call('recordSupplierPayment')
            ->assertHasNoErrors();

        $sale->refresh();
        $this->assertSame(40000, $sale->amountPaidToSupplierCents());
        $this->assertSame(0, $sale->supplierOutstandingCents());
    }

    public function test_a_voided_sale_cannot_take_a_customer_payment(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'status' => AgentSaleStatus::Voided,
        ]);
        $this->actingAsAgentUser($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->set('customerPaymentAmount', '100')
            ->set('customerPaymentDate', now()->format('Y-m-d'))
            ->call('recordCustomerPayment')
            ->assertStatus(403);

        $this->assertSame(0, $sale->fresh()->amountReceivedCents());
    }

    public function test_a_refunded_sale_cannot_take_a_supplier_payment(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'status' => AgentSaleStatus::Refunded,
        ]);
        $this->actingAsAgentOwner($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->set('supplierPaymentAmount', '100')
            ->set('supplierPaymentDate', now()->format('Y-m-d'))
            ->call('recordSupplierPayment')
            ->assertStatus(403);

        $this->assertSame(0, $sale->fresh()->amountPaidToSupplierCents());
    }

    public function test_markas_rejects_an_invalid_status(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create(['agent_organization_id' => $agent->id]);
        $this->actingAsAgentOwner($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->call('markAs', 'issued')
            ->assertHasErrors('status');

        $this->assertSame(AgentSaleStatus::Issued, $sale->fresh()->status);
    }

    public function test_a_sale_cannot_be_marked_twice(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create([
            'agent_organization_id' => $agent->id,
            'status' => AgentSaleStatus::Refunded,
        ]);
        $this->actingAsAgentOwner($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->call('markAs', 'voided')
            ->assertStatus(403);

        $this->assertSame(AgentSaleStatus::Refunded, $sale->fresh()->status);
    }

    public function test_an_agent_user_cannot_void_a_sale(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create(['agent_organization_id' => $agent->id]);
        $this->actingAsAgentUser($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->call('markAs', 'voided')
            ->assertStatus(403);

        $this->assertSame(AgentSaleStatus::Issued, $sale->fresh()->status);
    }

    public function test_an_owner_can_void_a_sale(): void
    {
        $agent = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create(['agent_organization_id' => $agent->id]);
        $this->actingAsAgentOwner($agent);

        Livewire::test('pages::sales.show', ['sale' => $sale])
            ->call('markAs', 'voided')
            ->assertHasNoErrors();

        $this->assertSame(AgentSaleStatus::Voided, $sale->fresh()->status);
    }

    public function test_a_user_cannot_view_another_agents_sale(): void
    {
        $agentA = Organization::factory()->agent()->create();
        $agentB = Organization::factory()->agent()->create();
        $sale = AgentSale::factory()->create(['agent_organization_id' => $agentB->id]);

        $this->actingAsAgentUser($agentA);

        Livewire::test('pages::sales.show', ['sale' => $sale])->assertStatus(403);
    }

    public function test_agent_sale_payment_direction_is_correctly_summed(): void
    {
        $sale = AgentSale::factory()->create(['purchase_price_cents' => 40000, 'sale_price_cents' => 55000]);
        $sale->payments()->create(['direction' => AgentSalePaymentDirection::FromCustomer, 'amount_cents' => 20000, 'paid_on' => now()]);
        $sale->payments()->create(['direction' => AgentSalePaymentDirection::FromCustomer, 'amount_cents' => 15000, 'paid_on' => now()]);
        $sale->payments()->create(['direction' => AgentSalePaymentDirection::ToSupplier, 'amount_cents' => 40000, 'paid_on' => now()]);

        $sale->refresh();

        $this->assertSame(35000, $sale->amountReceivedCents());
        $this->assertSame(40000, $sale->amountPaidToSupplierCents());
        $this->assertSame(20000, $sale->customerOutstandingCents());
        $this->assertSame(0, $sale->supplierOutstandingCents());
        $this->assertSame(15000, $sale->marginCents());
    }
}
