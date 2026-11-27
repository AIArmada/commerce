<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Actions;

use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use InvalidArgumentException;
use RuntimeException;

final readonly class DeleteCredentialDefinition
{
    public function execute(CredentialDefinition $record): bool
    {
        if (! $record->exists || $record->getKey() === null) {
            throw new InvalidArgumentException('CredentialDefinition must be persisted.');
        }

        return $record->getConnection()->transaction(function () use ($record): bool {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->delete()) {
                throw new RuntimeException('CredentialDefinition deletion was cancelled.');
            }

            return true;
        });
    }

    public function deleteAssignments(CredentialDefinition $record): void
    {
        $record->getConnection()->transaction(function () use ($record): void {
            $record->assignments()->eachById(static function (CredentialAssignment $assignment): void {
                if (! $assignment->delete()) {
                    throw new RuntimeException('CredentialDefinition assignment deletion was cancelled.');
                }
            }, 500);
        });
    }
}
