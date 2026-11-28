<?php

declare(strict_types=1);

namespace AIArmada\Customers\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 *
 * @phpstan-type RelatedCustomerNote \AIArmada\Customers\Features\Identity\Models\CustomerNote
 */
interface CustomerNoteRelationModel
{
    /** @return class-string<RelatedCustomerNote> */
    public function note(): string;
}
