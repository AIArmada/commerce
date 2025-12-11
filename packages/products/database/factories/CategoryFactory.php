<?php

declare(strict_types=1);

namespace AIArmada\Products\Database\Factories;

use AIArmada\Products\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

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
            'parent_id' => null,
            'position' => $this->faker->numberBetween(0, 100),
            'is_visible' => true,
            'is_featured' => $this->faker->boolean(20),
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /**
     * Hidden category.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }

    /**
     * Featured category.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Child category.
     */
    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}
