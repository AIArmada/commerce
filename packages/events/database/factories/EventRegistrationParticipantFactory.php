<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistrationParticipant>
 */
final class EventRegistrationParticipantFactory extends Factory
{
    protected $model = EventRegistrationParticipant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'status' => 'registered',
        ];
    }
}
