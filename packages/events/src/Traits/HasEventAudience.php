<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventAudience;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventAudience
{
    /**
     * @return MorphMany<EventAudience, $this>
     */
    public function audiences(): MorphMany
    {
        return $this->morphMany(EventAudience::class, 'audienceable');
    }
}
