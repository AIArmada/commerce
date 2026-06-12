<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventAttendance;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventAttendances
{
    /**
     * @return MorphMany<EventAttendance, $this>
     */
    public function attendances(): MorphMany
    {
        return $this->morphMany(EventAttendance::class, 'attendee');
    }
}
