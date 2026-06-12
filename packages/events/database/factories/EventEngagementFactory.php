<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventEngagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventEngagement>
 */
final class EventEngagementFactory extends Factory
{
    protected $model = EventEngagement::class;

    public function definition(): array
    {
        return [
            'engagement_type' => 'check_in',
        ];
    }
}
