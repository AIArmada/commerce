<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventOccurrence>
 */
final class EventOccurrenceFactory extends Factory
{
    protected $model = EventOccurrence::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'starts_at' => now()->addDays(rand(1, 30)),
            'ends_at' => now()->addDays(rand(1, 30))->addHours(2),
            'timezone' => 'UTC',
            'status' => 'scheduled',
            'visibility' => 'public',
        ];
    }
}
