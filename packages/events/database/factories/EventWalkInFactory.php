<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventWalkIn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventWalkIn>
 */
final class EventWalkInFactory extends Factory
{
    protected $model = EventWalkIn::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'count' => 1,
            'recorded_at' => now(),
        ];
    }
}
