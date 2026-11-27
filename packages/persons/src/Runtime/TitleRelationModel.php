<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 *
 * @phpstan-type RelatedTitleAssignment \AIArmada\Persons\Features\Titles\Models\TitleAssignment
 */
interface TitleRelationModel
{
    /** @return class-string<RelatedTitleAssignment> */
    public function assignment(): string;
}
