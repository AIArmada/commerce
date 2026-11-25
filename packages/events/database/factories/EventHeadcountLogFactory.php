<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Features\Attendance\Models\EventHeadcountLog;
use AIArmada\Events\Features\Catalog\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventHeadcountLog>
 */
final class EventHeadcountLogFactory extends Factory
{
    protected $model = EventHeadcountLog::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'count' => $this->faker->numberBetween(1, 500),
            'recorded_at' => now(),
        ];
    }
}
