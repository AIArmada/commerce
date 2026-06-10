<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Authorization;

use AIArmada\Events\Models\Event;
use AIArmada\FilamentAuthz\Authz;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait ManagesEvents
{
    public function organizedEvents(): MorphToMany
    {
        return $this->morphToMany(Event::class, 'reference', 'event_organizers');
    }

    public function speakingAt(): MorphToMany
    {
        return $this->morphToMany(Event::class, 'assignable', 'event_speakers');
    }

    public function canManageEvent(Event $event): bool
    {
        if (class_exists(Authz::class)) {
            foreach ($event->organizers()->get() as $organizer) {
                if ($organizer->reference && \AIArmada\FilamentAuthz\Facades\Authz::userCanInScope($this, 'event.manage', $organizer->reference)) {
                    return true;
                }
            }
        }

        return $event->people()
            ->where('person_type', $this->getMorphClass())
            ->where('person_id', $this->getKey())
            ->exists();
    }
}
