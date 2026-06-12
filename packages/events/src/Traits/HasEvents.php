<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEvents
{
    /**
     * @return MorphMany<Event, $this>
     */
    public function events(): MorphMany
    {
        return $this->morphMany(Event::class, 'owner');
    }
}
