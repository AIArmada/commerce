<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventPass;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventPasses
{
    /**
     * @return MorphMany<EventPass, $this>
     */
    public function passes(): MorphMany
    {
        return $this->morphMany(EventPass::class, 'passable');
    }
}
