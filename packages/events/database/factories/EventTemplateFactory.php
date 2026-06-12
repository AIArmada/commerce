<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTemplate>
 */
final class EventTemplateFactory extends Factory
{
    protected $model = EventTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'template_type' => 'event',
            'status' => 'draft',
            'visibility' => 'private',
            'payload' => [],
        ];
    }
}
