<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Actions;

use AIArmada\Persons\Features\Credentials\Api\PersonCredentialsCleaner;
use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use RuntimeException;

final readonly class DeletePersonCredentials implements PersonCredentialsCleaner
{
    public function cleanup(string $personId, string $personType): void
    {
        CredentialAssignment::query()
            ->where('credentialable_type', $personType)
            ->where('credentialable_id', $personId)
            ->eachById(static function (CredentialAssignment $record): void {
                if (! $record->delete()) {
                    throw new RuntimeException('Person dependent deletion was cancelled.');
                }
            }, 500);
    }
}
