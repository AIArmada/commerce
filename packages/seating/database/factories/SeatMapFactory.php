<?php

declare(strict_types=1);

namespace AIArmada\Seating\Database\Factories;

use AIArmada\Seating\Models\SeatMap;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SeatMapFactory extends Factory
{
    protected $model = SeatMap::class;

    public function definition(): array
    {
        return [
            'name' => 'Map-' . rand(10000, 99999),
            'slug' => 'map-' . rand(10000, 99999),
            'version' => 1,
        ];
    }

    public function active(): self
    {
        return $this->state(fn () => ['version' => 2]);
    }
}
