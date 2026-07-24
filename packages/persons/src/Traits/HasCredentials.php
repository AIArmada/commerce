<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Models\CredentialAssignment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Allows any model to receive credential assignments through the polymorphic
 * `credentialable` morph.
 */
trait HasCredentials
{
    /**
     * @return MorphMany<CredentialAssignment, $this>
     */
    public function credentialAssignments(): MorphMany
    {
        return $this->morphMany(CredentialAssignment::class, 'credentialable');
    }
}
