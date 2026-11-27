<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Runtime\CredentialRelationModel;
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
        return $this->morphMany(app(CredentialRelationModel::class)->assignment(), 'credentialable');
    }
}
