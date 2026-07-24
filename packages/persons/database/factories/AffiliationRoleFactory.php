<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Models\AffiliationRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliationRole>
 */
final class AffiliationRoleFactory extends Factory
{
    protected $model = AffiliationRole::class;

    public function definition(): array
    {
        return [
            'role_name' => $this->faker->jobTitle(),
            'is_current' => true,
        ];
    }
}
