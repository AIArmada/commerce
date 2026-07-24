<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Models\TitleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleCategory>
 */
final class TitleCategoryFactory extends Factory
{
    protected $model = TitleCategory::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->word(),
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
