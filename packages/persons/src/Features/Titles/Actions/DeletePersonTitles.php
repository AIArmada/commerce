<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Actions;

use AIArmada\Persons\Features\Titles\Api\PersonTitlesCleaner;
use AIArmada\Persons\Features\Titles\Models\TitleAssignment;

final readonly class DeletePersonTitles implements PersonTitlesCleaner
{
    public function cleanup(string $personId, string $personType): void
    {
        TitleAssignment::query()
            ->where('titleable_type', $personType)
            ->where('titleable_id', $personId)
            ->eachById(fn (TitleAssignment $record) => $record->delete(), 500);
    }
}
