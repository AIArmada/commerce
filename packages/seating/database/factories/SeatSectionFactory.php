<?php

declare(strict_types=1);

namespace AIArmada\Seating\Database\Factories;

use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SeatSectionFactory extends Factory
{
    protected $model = SeatSection::class;

    public function definition(): array
    {
        return [
            'seat_map_id' => SeatMap::factory(),
            'code' => chr(rand(65, 90)) . rand(0, 9),
            'name' => 'Section-' . rand(100, 999),
            'capacity' => $this->faker->numberBetween(10, 200),
            'sort_order' => $this->faker->numberBetween(0, 20),
        ];
    }
}
