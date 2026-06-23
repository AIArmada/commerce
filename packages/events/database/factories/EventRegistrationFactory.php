<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistration>
 */
final class EventRegistrationFactory extends Factory
{
    protected $model = EventRegistration::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'registration_no' => 'REG-' . mb_strtoupper($this->faker->bothify('??####')),
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'source' => 'website',
            'total_participants' => 1,
            'registered_at' => now(),
        ];
    }
}
