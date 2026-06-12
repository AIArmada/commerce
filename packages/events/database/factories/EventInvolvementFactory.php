<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventInvolvement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventInvolvement>
 */
final class EventInvolvementFactory extends Factory
{
    protected $model = EventInvolvement::class;

    public function definition(): array
    {
        return [
            'status' => 'active',
            'visibility' => 'public',
        ];
    }
}
