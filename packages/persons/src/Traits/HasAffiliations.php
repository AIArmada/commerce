<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Models\Affiliation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Allows any model to be linked to institutions through the polymorphic
 * `affiliatable` morph.
 */
trait HasAffiliations
{
    /**
     * @return MorphMany<Affiliation, $this>
     */
    public function affiliations(): MorphMany
    {
        return $this->morphMany(Affiliation::class, 'affiliatable');
    }
}
