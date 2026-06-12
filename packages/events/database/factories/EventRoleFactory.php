<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRole>
 */
final class EventRoleFactory extends Factory
{
    protected $model = EventRole::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('role_????'),
            'name' => $this->faker->word() . ' Role',
        ];
    }
}
