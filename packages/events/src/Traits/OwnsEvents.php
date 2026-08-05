<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\ModelResolver;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait OwnsEvents
{
    /**
     * @return MorphMany<Event, $this>
     */
    public function ownedEvents(): MorphMany
    {
        return $this->morphMany(ModelResolver::eventClass(), 'owner');
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
