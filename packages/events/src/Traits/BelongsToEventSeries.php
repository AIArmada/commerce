<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait BelongsToEventSeries
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function series(): MorphTo
    {
        return $this->morphTo();
    }
}
