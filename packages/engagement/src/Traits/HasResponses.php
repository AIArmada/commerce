<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasResponses
{
    /**
     * @return MorphMany<Response, $this>
     */
    public function responses(): MorphMany
    {
        return $this->morphMany(Response::class, 'respondable');
    }

    /**
     * @param Builder<Response> $query
     * @return Builder<Response>
     */
    public function scopeActiveResponses(Builder $query): Builder
    {
        return $query->whereHas('responses', function (Builder $q) {
            $q->active();
        });
    }
}
