<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventClassification;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEventClassifications
{
    /**
     * @return MorphMany<EventClassification, $this>
     */
    public function classifications(): MorphMany
    {
        return $this->morphMany(EventClassification::class, 'classifiable');
    }
}
