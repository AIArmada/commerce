<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Title>
 */
final class TitleFactory extends Factory
{
    protected $model = Title::class;

    public function definition(): array
    {
        return [
            'category_id' => TitleCategory::factory(),
            'name' => $this->faker->word(),
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
