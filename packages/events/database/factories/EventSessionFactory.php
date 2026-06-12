<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSession>
 */
final class EventSessionFactory extends Factory
{
    protected $model = EventSession::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'summary' => $this->faker->paragraph(),
            'status' => 'scheduled',
            'visibility' => 'public',
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
