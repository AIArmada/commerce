<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'slug' => Str::slug($this->faker->unique()->sentence(3)),
            'summary' => $this->faker->paragraph(),
            'description' => $this->faker->text(),
            'status' => Event::DRAFT,
            'visibility' => Event::PUBLIC,
            'delivery_mode' => Event::DELIVERY_PHYSICAL,
            'timezone' => 'UTC',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => Event::PUBLISHED, 'published_at' => now()]);
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

    public function mixed(): static
    {
        return $this->state(fn () => ['pricing_mode' => PricingMode::Mixed]);
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
