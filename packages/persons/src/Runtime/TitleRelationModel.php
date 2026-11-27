<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface TitleRelationModel
{
    /** @return class-string<\AIArmada\Persons\Features\Titles\Models\TitleAssignment> */
    public function assignment(): string;
}
