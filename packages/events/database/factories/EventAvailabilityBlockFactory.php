<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventAvailabilityBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAvailabilityBlock>
 */
final class EventAvailabilityBlockFactory extends Factory
{
    protected $model = EventAvailabilityBlock::class;

    public function definition(): array
    {
        return [
            'blockable_type' => $this->faker->word(),
            'blockable_id' => (string) $this->faker->uuid(),
            'block_type' => 'maintenance',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'timezone' => 'UTC',
            'status' => 'active',
            'visibility' => 'internal',
        ];
    }
}
