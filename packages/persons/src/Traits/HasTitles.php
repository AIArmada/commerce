<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Runtime\TitleRelationModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Allows any model (Person, Institution, Venue, ...) to receive title
 * assignments through the polymorphic `titleable` morph.
 *
 * @phpstan-type RelatedTitleAssignment \AIArmada\Persons\Features\Titles\Models\TitleAssignment
 */
trait HasTitles
{
    /**
     * @return MorphMany<RelatedTitleAssignment, $this>
     */
    public function titleAssignments(): MorphMany
    {
        return $this->morphMany(app(TitleRelationModel::class)->assignment(), 'titleable');
    }
}
