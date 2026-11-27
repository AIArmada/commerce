<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;

/**
 * Internal Eloquent relationship composition, not a cross-feature domain API.
 * The owning feature supplies the related model classes.
 */
interface CredentialRelationModel
{
    /** @return class-string<CredentialAssignment> */
    public function assignment(): string;
}
