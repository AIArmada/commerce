<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface PersonNameRelationModel
{
    /** @return class-string<\AIArmada\Persons\Features\Identity\Models\PersonName> */
    public function name(): string;
}
