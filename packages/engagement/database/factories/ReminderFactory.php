<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Enums\ReminderStatus;
use AIArmada\Engagement\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
final class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'reminder_type' => 'event',
            'status' => ReminderStatus::Pending,
        ];
    }
}
