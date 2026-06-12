<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

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
}
