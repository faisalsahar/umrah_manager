<?php

namespace Database\Factories;

use App\Enums\HotelStaySource;
use App\Enums\HotelStayStatus;
use App\Models\Hotel;
use App\Models\HotelStay;
use App\Models\Pilgrim;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelStay>
 */
class HotelStayFactory extends Factory
{
    protected $model = HotelStay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 week', '+2 months');
        $pilgrim = Pilgrim::factory();

        return [
            'operating_order_id' => null,
            'hotel_id' => Hotel::factory(),
            'room_type_id' => RoomType::factory(),
            'pilgrim_id' => $pilgrim,
            'gender' => null, // backfilled from the pilgrim by HotelStay::booted()
            'room_no' => null,
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->modify('+'.fake()->numberBetween(3, 10).' days'),
            'status' => HotelStayStatus::Booked,
            'source' => HotelStaySource::OperatorSold,
        ];
    }

    public function agentProvided(): static
    {
        return $this->state(fn (): array => [
            'operating_order_id' => null,
            'source' => HotelStaySource::AgentProvided,
        ]);
    }
}
