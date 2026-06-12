<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeat>
 */
final class EventSeatFactory extends Factory
{
    protected $model = EventSeat::class;

    public function definition(): array
    {
        return [
            'row_label' => $this->faker->randomLetter(),
            'seat_number' => (string) $this->faker->numberBetween(1, 50),
            'label' => $this->faker->randomLetter() . $this->faker->numberBetween(1, 50),
            'status' => 'available',
        ];
    }
}
