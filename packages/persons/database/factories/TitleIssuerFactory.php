<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Enums\IssuerType;
use AIArmada\Persons\Models\TitleIssuer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleIssuer>
 */
final class TitleIssuerFactory extends Factory
{
    protected $model = TitleIssuer::class;

    public function definition(): array
    {
        return [
            'issuer_name' => $this->faker->company(),
            'issuer_type' => IssuerType::Government,
        ];
    }
}
