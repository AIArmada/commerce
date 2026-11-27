<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

use AIArmada\Persons\Features\Identity\Models\PersonName;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface PersonNameRelationModel
{
    /** @return class-string<PersonName> */
    public function name(): string;
}
