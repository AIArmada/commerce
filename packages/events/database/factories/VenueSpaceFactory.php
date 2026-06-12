<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\VenueSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenueSpace>
 */
final class VenueSpaceFactory extends Factory
{
    protected $model = VenueSpace::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Space',
            'status' => 'active',
            'visibility' => 'public',
        ];
    }
}
