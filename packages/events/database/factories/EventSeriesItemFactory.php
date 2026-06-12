<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeriesItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeriesItem>
 */
final class EventSeriesItemFactory extends Factory
{
    protected $model = EventSeriesItem::class;

    public function definition(): array
    {
        return [];
    }
}
