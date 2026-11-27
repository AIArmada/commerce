<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Runtime\AffiliationRelationModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Allows any model to be linked to institutions through the polymorphic
 * `affiliatable` morph.
 */
trait HasAffiliations
{
    /**
     * @return MorphMany<\AIArmada\Persons\Features\Affiliations\Models\Affiliation, $this>
     */
    public function affiliations(): MorphMany
    {
        return $this->morphMany(app(AffiliationRelationModel::class)->affiliation(), 'affiliatable');
    }
}
