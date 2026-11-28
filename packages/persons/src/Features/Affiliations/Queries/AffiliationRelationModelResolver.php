<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Queries;

use AIArmada\Persons\Features\Affiliations\Models\Affiliation;
use AIArmada\Persons\Runtime\AffiliationRelationModel;

final readonly class AffiliationRelationModelResolver implements AffiliationRelationModel
{
    public function affiliation(): string
    {
        return Affiliation::class;
    }
}
