<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventMedia>
 */
final class EventMediaFactory extends Factory
{
    protected $model = EventMedia::class;

    public function definition(): array
    {
        return [
            'media_type' => 'image',
            'usage_type' => 'gallery',
            'title' => $this->faker->sentence(3),
            'url' => $this->faker->imageUrl(),
            'visibility' => 'public',
        ];
    }
}
