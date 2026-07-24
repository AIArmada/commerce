<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\CredentialAssignment;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CredentialAssignmentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $credential_id,
        public readonly string $status,
        public readonly string | null | Optional $registration_number,
        public readonly string | null | Optional $date_obtained,
        public readonly CredentialDefinitionData | null | Optional $credential,
    ) {}

    public static function fromCredentialAssignment(CredentialAssignment $assignment): self
    {
        return new self(
            id: $assignment->id,
            credential_id: $assignment->credential_id,
            status: $assignment->status->value,
            registration_number: $assignment->registration_number,
            date_obtained: $assignment->date_obtained?->format('Y-m-d'),
            credential: $assignment->relationLoaded('credential') && $assignment->credential
                ? CredentialDefinitionData::fromCredentialDefinition($assignment->credential)
                : null,
        );
    }
}
