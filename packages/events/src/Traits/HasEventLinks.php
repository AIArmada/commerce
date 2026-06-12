<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventLink;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventLinks
{
    /**
     * @return MorphMany<EventLink, $this>
     */
    public function links(): MorphMany
    {
        return $this->morphMany(EventLink::class, 'linkable');
    }
}
