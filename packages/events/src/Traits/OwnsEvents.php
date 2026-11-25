<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Data\EventVisibility;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait OwnsEvents
{
    /**
     * @return MorphMany<Model, $this>
     */
    public function ownedEvents(): MorphMany
    {
        return $this->morphMany(ModelResolver::eventClass(), 'owner');
    }

    public function defaultEventVisibility(): string
    {
        return EventVisibility::Public->value;
    }

    public function defaultEventApprovalRequired(): bool
    {
        return false;
    }
}
