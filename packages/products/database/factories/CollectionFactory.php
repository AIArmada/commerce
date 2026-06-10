<?php

declare(strict_types=1);

namespace AIArmada\Products\Database\Factories;

use AIArmada\Products\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'type' => 'manual',
            'position' => $this->faker->numberBetween(0, 100),
            'status' => 'active',
            'visibility' => 'catalog',
            'is_featured' => $this->faker->boolean(20),
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'hidden',
            'hidden_at' => now(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'automatic',
            'conditions' => [
                ['field' => 'type', 'operator' => '=', 'value' => 'simple'],
            ],
        ]);
    }

    public function scheduled(?string $publishAt = null, ?string $unpublishAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => $publishAt ?? now()->addDays(7),
            'unpublished_at' => $unpublishAt,
        ]);
    }
}
