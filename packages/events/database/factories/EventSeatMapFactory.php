<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeatMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeatMap>
 */
final class EventSeatMapFactory extends Factory
{
    protected $model = EventSeatMap::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Seat Map',
            'status' => 'active',
        ];
    }
}
