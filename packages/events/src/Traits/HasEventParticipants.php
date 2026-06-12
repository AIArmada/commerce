<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventRegistrationParticipant;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventParticipants
{
    /**
     * @return MorphMany<EventRegistrationParticipant, $this>
     */
    public function participants(): MorphMany
    {
        return $this->morphMany(EventRegistrationParticipant::class, 'participant');
    }
}
