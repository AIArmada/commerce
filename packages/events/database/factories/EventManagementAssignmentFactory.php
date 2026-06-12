<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventManagementAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventManagementAssignment>
 */
final class EventManagementAssignmentFactory extends Factory
{
    protected $model = EventManagementAssignment::class;

    public function definition(): array
    {
        return [
            'role' => 'manager',
        ];
    }
}
