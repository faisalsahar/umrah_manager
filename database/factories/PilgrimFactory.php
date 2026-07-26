<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Organization;
use App\Models\Pilgrim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pilgrim>
 */
class PilgrimFactory extends Factory
{
    protected $model = Pilgrim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'operator_id' => Organization::factory()->operator(),
            'agent_id' => Organization::factory()->agent(),
            'group_id' => null,
            'name' => $gender === Gender::Male ? fake()->name('male') : fake()->name('female'),
            'passport_number' => strtoupper(fake()->bothify('??#######')),
            'nationality' => fake()->countryCode(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-75 years', '-12 years'),
            'phone' => fake()->e164PhoneNumber(),
            'mahram_of_pilgrim_id' => null,
        ];
    }

    public function male(): static
    {
        return $this->state(fn (): array => ['gender' => Gender::Male, 'name' => fake()->name('male')]);
    }

    public function female(): static
    {
        return $this->state(fn (): array => ['gender' => Gender::Female, 'name' => fake()->name('female')]);
    }
}
