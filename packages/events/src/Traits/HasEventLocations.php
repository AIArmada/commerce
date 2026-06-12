<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventLocation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventLocations
{
    /**
     * @return MorphMany<EventLocation, $this>
     */
    public function eventLocations(): MorphMany
    {
        return $this->morphMany(EventLocation::class, 'locatable');
    }
}
