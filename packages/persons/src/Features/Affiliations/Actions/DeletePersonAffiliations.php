<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Actions;

use AIArmada\Persons\Features\Affiliations\Api\PersonAffiliationsCleaner;
use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use RuntimeException;

final readonly class DeletePersonAffiliations implements PersonAffiliationsCleaner
{
    public function cleanup(string $personId, string $personType): void
    {
        Affiliation::query()
            ->where('affiliatable_type', $personType)
            ->where('affiliatable_id', $personId)
            ->eachById(static function (Affiliation $record): void {
                if (! $record->delete()) {
                    throw new RuntimeException('Person dependent deletion was cancelled.');
                }
            }, 500);
    }
}
