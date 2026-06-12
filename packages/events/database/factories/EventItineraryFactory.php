<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventItinerary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventItinerary>
 */
final class EventItineraryFactory extends Factory
{
    protected $model = EventItinerary::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'itinerary_type' => 'schedule',
            'visibility' => 'public',
            'status' => 'active',
        ];
    }
}
