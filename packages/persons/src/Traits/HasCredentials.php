<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Runtime\CredentialRelationModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Allows any model to receive credential assignments through the polymorphic
 * `credentialable` morph.
 *
 * @phpstan-type RelatedCredentialAssignment \AIArmada\Persons\Features\Credentials\Models\CredentialAssignment
 */
trait HasCredentials
{
    /**
     * @return MorphMany<RelatedCredentialAssignment, $this>
     */
    public function credentialAssignments(): MorphMany
    {
        return $this->morphMany(app(CredentialRelationModel::class)->assignment(), 'credentialable');
    }
}
