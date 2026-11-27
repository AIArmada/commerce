<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 *
 * @phpstan-type RelatedPersonName \AIArmada\Persons\Features\Identity\Models\PersonName
 */
interface PersonNameRelationModel
{
    /** @return class-string<RelatedPersonName> */
    public function name(): string;
}
