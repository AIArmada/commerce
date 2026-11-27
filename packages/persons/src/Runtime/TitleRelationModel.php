<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

use AIArmada\Persons\Features\Titles\Models\TitleAssignment;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface TitleRelationModel
{
    /** @return class-string<TitleAssignment> */
    public function assignment(): string;
}
