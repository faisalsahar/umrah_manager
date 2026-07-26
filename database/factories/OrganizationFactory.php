<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => OrganizationType::Operator,
            'name' => fake()->company(),
            'country' => fake()->countryCode(),
            'license_no' => null,
            'is_active' => true,
        ];
    }

    public function operator(): static
    {
        return $this->state(['type' => OrganizationType::Operator, 'country' => 'SA']);
    }

    public function agent(): static
    {
        return $this->state(['type' => OrganizationType::Agent]);
    }

    public function hotel(): static
    {
        return $this->state(['type' => OrganizationType::Hotel, 'country' => 'SA']);
    }

    public function transportSupplier(): static
    {
        return $this->state(['type' => OrganizationType::TransportSupplier, 'country' => 'SA']);
    }
}
