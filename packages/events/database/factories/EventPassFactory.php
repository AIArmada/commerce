<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPass>
 */
final class EventPassFactory extends Factory
{
    protected $model = EventPass::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'pass_no' => $this->faker->unique()->lexify('PASS-????????'),
            'status' => 'issued',
        ];
    }
}
