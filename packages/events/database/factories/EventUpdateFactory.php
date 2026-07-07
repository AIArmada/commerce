<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventUpdate>
 */
class EventUpdateFactory extends Factory
{
    protected $model = EventUpdate::class;

    public function definition(): array
    {
        return [
            'update_type' => 'general',
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(),
            'severity' => 'info',
            'visibility' => 'public',
        ];
    }
}
