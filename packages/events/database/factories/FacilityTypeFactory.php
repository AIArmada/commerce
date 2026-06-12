<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\FacilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityType>
 */
final class FacilityTypeFactory extends Factory
{
    protected $model = FacilityType::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('facility_????'),
            'name' => $this->faker->word() . ' Facility',
        ];
    }
}
