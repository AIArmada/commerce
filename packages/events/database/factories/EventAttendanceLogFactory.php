<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAttendanceLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendanceLog>
 */
final class EventAttendanceLogFactory extends Factory
{
    protected $model = EventAttendanceLog::class;

    public function definition(): array
    {
        return [
            'action' => 'checked_in',
            'occurred_at' => now(),
        ];
    }
}
