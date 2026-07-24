<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Enums\CredentialType;
use AIArmada\Persons\Models\CredentialDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CredentialDefinition>
 */
final class CredentialDefinitionFactory extends Factory
{
    protected $model = CredentialDefinition::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'short_form' => $this->faker->unique()->lexify('???'),
            'credential_type' => CredentialType::AcademicDegree,
        ];
    }
}
