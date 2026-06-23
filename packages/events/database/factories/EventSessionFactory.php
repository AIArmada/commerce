<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventSession>
 */
final class EventSessionFactory extends Factory
{
    protected $model = EventSession::class;

    public function definition(): array
    {
        $eventId = (string) Str::uuid();
        $occurrenceId = (string) Str::uuid();

        return [
            'event_id' => Event::factory()->state(['id' => $eventId]),
            'event_occurrence_id' => EventOccurrence::factory()->state([
                'id' => $occurrenceId,
                'event_id' => $eventId,
            ]),
            'title' => $this->faker->sentence(3),
            'summary' => $this->faker->paragraph(),
            'status' => 'scheduled',
            'visibility' => 'public',
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    public function pricingMode(PricingMode $mode): static
    {
        return $this->state(fn () => ['pricing_mode' => $mode]);
    }

    public function registrationMode(RegistrationMode $mode): static
    {
        return $this->state(fn () => ['registration_mode' => $mode]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['pricing_mode' => PricingMode::Free]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['pricing_mode' => PricingMode::Paid]);
    }

    public function freeWithOptionalRegistration(): static
    {
        return $this->state(fn () => [
            'pricing_mode' => PricingMode::Free,
            'registration_mode' => RegistrationMode::Optional,
        ]);
    }

    public function freeOpenDoor(): static
    {
        return $this->state(fn () => [
            'pricing_mode' => PricingMode::Free,
            'registration_mode' => RegistrationMode::None,
        ]);
    }
}
