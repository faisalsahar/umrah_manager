<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->operator(),
            'counterparty_organization_id' => Organization::factory()->agent(),
            'operating_order_id' => null,
            'entry_type' => LedgerEntryType::Charge,
            'amount_cents' => fake()->numberBetween(10000, 500000),
            'currency' => 'SAR',
            'description' => fake()->sentence(4),
            'occurred_on' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
