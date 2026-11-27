<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Actions;

use AIArmada\Persons\Features\Credentials\Api\PersonCredentialsCleaner;
use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;

final readonly class DeletePersonCredentials implements PersonCredentialsCleaner
{
    public function cleanup(string $personId, string $personType): void
    {
        CredentialAssignment::query()
            ->where('credentialable_type', $personType)
            ->where('credentialable_id', $personId)
            ->eachById(fn (CredentialAssignment $record) => $record->delete(), 500);
    }
}
