<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeries;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventSeries>
 */
final class EventSeriesFactory extends Factory
{
    protected $model = EventSeries::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'slug' => Str::slug($this->faker->unique()->sentence(2)),
            'series_type' => 'recurring',
            'status' => 'active',
            'visibility' => 'public',
        ];
    }
}
