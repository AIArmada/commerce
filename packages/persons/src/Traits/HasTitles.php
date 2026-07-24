<?php

declare(strict_types=1);

namespace AIArmada\Persons\Traits;

use AIArmada\Persons\Models\TitleAssignment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Allows any model (Person, Institution, Venue, ...) to receive title
 * assignments through the polymorphic `titleable` morph.
 */
trait HasTitles
{
    /**
     * @return MorphMany<TitleAssignment, $this>
     */
    public function titleAssignments(): MorphMany
    {
        return $this->morphMany(TitleAssignment::class, 'titleable');
    }
}
