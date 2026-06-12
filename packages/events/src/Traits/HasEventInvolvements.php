<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventInvolvement;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventInvolvements
{
    /**
     * @return MorphMany<EventInvolvement, $this>
     */
    public function involvements(): MorphMany
    {
        return $this->morphMany(EventInvolvement::class, 'involvable');
    }
}
