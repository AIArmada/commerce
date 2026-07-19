<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTerm;
use AIArmada\Events\Models\EventTermPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTermPolicy>
 */
final class EventTermPolicyFactory extends Factory
{
    protected $model = EventTermPolicy::class;

    public function definition(): array
    {
        return [
            'event_term_id' => EventTerm::factory(),
            'policy_code' => $this->faker->randomElement(['requires_speaker', 'requires_physical_delivery']),
            'is_enabled' => true,
        ];
    }
}
