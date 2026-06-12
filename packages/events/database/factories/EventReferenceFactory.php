<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReference>
 */
final class EventReferenceFactory extends Factory
{
    protected $model = EventReference::class;

    public function definition(): array
    {
        return [
            'reference_type' => 'website',
            'title' => $this->faker->sentence(3),
            'url' => $this->faker->url(),
            'visibility' => 'public',
        ];
    }
}
