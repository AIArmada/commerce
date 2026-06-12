<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSeatHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeatHold>
 */
final class EventSeatHoldFactory extends Factory
{
    protected $model = EventSeatHold::class;

    public function definition(): array
    {
        return [
            'quantity' => 1,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
