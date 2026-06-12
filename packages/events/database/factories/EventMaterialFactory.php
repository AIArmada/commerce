<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventMaterial>
 */
final class EventMaterialFactory extends Factory
{
    protected $model = EventMaterial::class;

    public function definition(): array
    {
        return [
            'material_type' => 'document',
            'usage_type' => 'reference',
            'title' => $this->faker->sentence(3),
            'url' => $this->faker->url(),
            'visibility' => 'public',
        ];
    }
}
