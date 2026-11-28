<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 *
 * @phpstan-type RelatedCredentialAssignment \AIArmada\Persons\Features\Credentials\Models\CredentialAssignment
 */
interface CredentialRelationModel
{
    /** @return class-string<RelatedCredentialAssignment> */
    public function assignment(): string;
}
