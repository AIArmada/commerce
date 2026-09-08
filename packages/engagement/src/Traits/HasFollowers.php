<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Follow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasFollowers
{
    use ReceivesEngagement;

    /**
     * @return MorphMany<Follow, $this>
     */
    public function follows(): MorphMany
    {
        return $this->engagementRelation(Follow::class, 'followable');
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeActiveFollows(Builder $query): Builder
    {
        return $this->activeEngagementScope($query, 'follows');
    }

    public function followersCount(): int
    {
        return $this->engagementCount('followers');
    }
}
