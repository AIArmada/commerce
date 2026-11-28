<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleAssignment;
use InvalidArgumentException;
use RuntimeException;

final readonly class DeleteTitle
{
    public function execute(Title $record): bool
    {
        if (! $record->exists || $record->getKey() === null) {
            throw new InvalidArgumentException('Title must be persisted.');
        }

        return $record->getConnection()->transaction(function () use ($record): bool {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->delete()) {
                throw new RuntimeException('Title deletion was cancelled.');
            }

            return true;
        });
    }

    public function deleteAssignments(Title $record): void
    {
        $record->getConnection()->transaction(function () use ($record): void {
            $record->assignments()->eachById(static function (TitleAssignment $assignment): void {
                if (! $assignment->delete()) {
                    throw new RuntimeException('Title assignment deletion was cancelled.');
                }
            }, 500);
        });
    }
}
