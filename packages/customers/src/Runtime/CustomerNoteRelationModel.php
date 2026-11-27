<?php

declare(strict_types=1);

namespace AIArmada\Customers\Runtime;

use AIArmada\Customers\Features\Identity\Models\CustomerNote;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface CustomerNoteRelationModel
{
    /** @return class-string<CustomerNote> */
    public function note(): string;
}
