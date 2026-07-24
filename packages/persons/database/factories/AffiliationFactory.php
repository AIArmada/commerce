<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Enums\AffiliationType;
use AIArmada\Persons\Models\Affiliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affiliation>
 */
final class AffiliationFactory extends Factory
{
    protected $model = Affiliation::class;

    public function definition(): array
    {
        return [
            'affiliatable_type' => 'person',
            'affiliation_type' => AffiliationType::Employee,
            'is_primary' => false,
        ];
    }
}
