<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventNotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventNotificationBatch>
 */
final class EventNotificationBatchFactory extends Factory
{
    protected $model = EventNotificationBatch::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'audience_scope' => 'all_registrants',
            'title' => $this->faker->sentence(4),
            'status' => 'draft',
        ];
    }
}
