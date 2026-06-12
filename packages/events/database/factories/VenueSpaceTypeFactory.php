<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\VenueSpaceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenueSpaceType>
 */
final class VenueSpaceTypeFactory extends Factory
{
    protected $model = VenueSpaceType::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('space_????'),
            'name' => $this->faker->word() . ' Space Type',
        ];
    }
}
