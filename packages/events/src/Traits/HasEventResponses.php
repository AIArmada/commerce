<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventResponse;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventResponses
{
    /**
     * @return MorphMany<EventResponse, $this>
     */
    public function responses(): MorphMany
    {
        return $this->morphMany(EventResponse::class, 'respondable');
    }
}
