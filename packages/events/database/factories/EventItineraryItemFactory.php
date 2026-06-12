<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventItineraryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventItineraryItem>
 */
final class EventItineraryItemFactory extends Factory
{
    protected $model = EventItineraryItem::class;

    public function definition(): array
    {
        return [
            'item_type' => 'session',
            'title' => $this->faker->sentence(3),
        ];
    }
}
