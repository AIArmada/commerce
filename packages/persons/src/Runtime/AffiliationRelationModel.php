<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 *
 * @phpstan-type RelatedAffiliation \AIArmada\Persons\Features\Affiliations\Models\Affiliation
 */
interface AffiliationRelationModel
{
    /** @return class-string<RelatedAffiliation> */
    public function affiliation(): string;
}
