<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeatAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeatAllocation>
 */
final class EventSeatAllocationFactory extends Factory
{
    protected $model = EventSeatAllocation::class;

    public function definition(): array
    {
        return [
            'allocation_type' => 'assigned',
            'status' => 'active',
            'allocated_at' => now(),
        ];
    }
}
