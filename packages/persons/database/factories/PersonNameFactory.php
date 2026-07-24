<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Models\PersonName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonName>
 */
final class PersonNameFactory extends Factory
{
    protected $model = PersonName::class;

    public function definition(): array
    {
        return [
            'name_type' => PersonNameType::Display,
            'full_name' => $this->faker->name(),
            'language_code' => 'en',
            'is_primary' => false,
        ];
    }
}
