<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Actions;

use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use AIArmada\Persons\Features\Affiliations\Models\AffiliationRole;

final readonly class DeleteAffiliationRoles
{
    public function execute(string $affiliationId): void
    {
        (new Affiliation)->getConnection()->transaction(function () use ($affiliationId): void {
            $affiliation = Affiliation::query()->whereKey($affiliationId)->lockForUpdate()->firstOrFail();
            $affiliation->roles()->eachById(fn (AffiliationRole $role) => $role->delete(), 500);
        });
    }
}
