<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\ModelResolver;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEvents
{
    /**
     * @return MorphMany<Event, $this>
     */
    public function events(): MorphMany
    {
        return $this->morphMany(ModelResolver::eventClass(), 'owner');
    }
}
