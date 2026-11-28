<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Queries;

use AIArmada\Persons\Data\CredentialAssignmentData;
use AIArmada\Persons\Data\CredentialDefinitionData;
use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;

final readonly class CredentialDataMapper
{
    public function fromCredentialAssignment(CredentialAssignment $assignment): CredentialAssignmentData
    {
        return new CredentialAssignmentData(
            id: $assignment->id,
            credential_id: $assignment->credential_id,
            status: $assignment->status->value,
            registration_number: $assignment->registration_number,
            date_obtained: $assignment->date_obtained?->format('Y-m-d'),
            credential: $assignment->relationLoaded('credential') && $assignment->credential
                ? $this->fromCredentialDefinition($assignment->credential)
                : null,
        );
    }

    public function fromCredentialDefinition(CredentialDefinition $definition): CredentialDefinitionData
    {
        return new CredentialDefinitionData(
            id: $definition->id,
            name: $definition->name,
            short_form: $definition->short_form,
            field: $definition->field,
            credential_type: $definition->credential_type->value,
        );
    }
}
