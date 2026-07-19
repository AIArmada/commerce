<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Enums\EventEscalationType;
use AIArmada\Events\Models\EventEscalation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventEscalation>
 */
final class EventEscalationFactory extends Factory
{
    protected $model = EventEscalation::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'type' => EventEscalationType::ModeratorSla,
            'decision_key' => Str::uuid()->toString().':moderator_sla',
            'reason' => $this->faker->sentence(),
        ];
    }
}
