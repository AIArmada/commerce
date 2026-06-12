<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventRecurrenceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRecurrenceRule>
 */
final class EventRecurrenceRuleFactory extends Factory
{
    protected $model = EventRecurrenceRule::class;

    public function definition(): array
    {
        return [
            'recurrence_type' => 'rrule',
            'frequency' => 'weekly',
            'interval' => 1,
            'timezone' => 'UTC',
            'status' => 'active',
            'visibility' => 'public',
        ];
    }
}
