<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Follow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasFollowers
{
    /**
     * @return MorphMany<Follow, $this>
     */
    public function follows(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    /**
     * @param Builder<Follow> $query
     * @return Builder<Follow>
     */
    public function scopeActiveFollows(Builder $query): Builder
    {
        return $query->whereHas('follows', function (Builder $q) {
            $q->active();
        });
    }

    public function followersCount(): int
    {
        return $this->follows()->active()->count();
    }
}
