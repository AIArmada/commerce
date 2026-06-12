<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventChangeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventChangeLog>
 */
final class EventChangeLogFactory extends Factory
{
    protected $model = EventChangeLog::class;

    public function definition(): array
    {
        return [
            'subject_type' => 'event',
            'change_type' => 'updated',
            'change_category' => 'details',
            'impact_level' => 'minor',
            'visibility' => 'public',
            'changed_at' => now(),
        ];
    }
}
