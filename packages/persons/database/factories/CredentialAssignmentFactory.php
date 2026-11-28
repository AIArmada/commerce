<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CredentialAssignment>
 */
final class CredentialAssignmentFactory extends Factory
{
    protected $model = CredentialAssignment::class;

    public function definition(): array
    {
        return [
            'credentialable_type' => 'person',
            'credential_id' => CredentialDefinition::factory(),
            'status' => 'active',
        ];
    }
}
