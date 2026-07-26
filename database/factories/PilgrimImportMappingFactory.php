<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PilgrimImportMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilgrimImportMapping>
 */
class PilgrimImportMappingFactory extends Factory
{
    protected $model = PilgrimImportMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $headers = ['Name', 'Passport Number'];

        return [
            'operator_id' => Organization::factory()->operator(),
            'headers_signature' => PilgrimImportMapping::signatureFor($headers),
            'mapping' => [
                'Name' => 'name',
                'Passport Number' => 'passport_number',
            ],
        ];
    }
}
