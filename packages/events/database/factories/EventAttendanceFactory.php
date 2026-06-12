<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendance>
 */
final class EventAttendanceFactory extends Factory
{
    protected $model = EventAttendance::class;

    public function definition(): array
    {
        return [
            'attendance_type' => 'in_person',
        ];
    }
}
