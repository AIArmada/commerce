<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventMedia;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventMedia
{
    /**
     * @return MorphMany<EventMedia, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(EventMedia::class, 'mediable');
    }
}
