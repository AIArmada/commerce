<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait OwnsEvents
{
    /**
     * @return MorphMany<Event, $this>
     */
    public function ownedEvents(): MorphMany
    {
        return $this->morphMany(Event::class, 'owner');
    }

    public function defaultEventVisibility(): string
    {
        return Event::PUBLIC;
    }

    public function defaultEventApprovalRequired(): bool
    {
        return false;
    }
}
