<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\HotelStaySource;
use App\Models\HotelStay;
use App\Models\Pilgrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelStayTest extends TestCase
{
    use RefreshDatabase;

    public function test_stay_gender_is_backfilled_from_the_pilgrim(): void
    {
        $pilgrim = Pilgrim::factory()->female()->create();

        $stay = HotelStay::factory()->create(['pilgrim_id' => $pilgrim->id, 'gender' => null]);

        $this->assertSame(Gender::Female, $stay->fresh()->gender);
    }

    public function test_a_stay_can_exist_with_no_operating_order_for_an_agent_provided_booking(): void
    {
        // The pilgrim slept somewhere the operator never sold — see docs/PLAN.md §3:
        // the operator still needs to know where, for transport and visa purposes.
        $stay = HotelStay::factory()->agentProvided()->create();

        $this->assertNull($stay->operating_order_id);
        $this->assertSame(HotelStaySource::AgentProvided, $stay->source);
    }
}
