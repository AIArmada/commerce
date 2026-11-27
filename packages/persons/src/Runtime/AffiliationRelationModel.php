<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

use AIArmada\Persons\Features\Affiliations\Models\Affiliation;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface AffiliationRelationModel
{
    /** @return class-string<Affiliation> */
    public function affiliation(): string;
}
