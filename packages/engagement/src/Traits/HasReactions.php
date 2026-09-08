<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Reaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasReactions
{
    use ReceivesEngagement;

    /**
     * @return MorphMany<Reaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->engagementRelation(Reaction::class, 'reactable');
    }

    /**
     * @param  Builder<Reaction>  $query
     * @return Builder<Reaction>
     */
    public function scopeActiveReactions(Builder $query): Builder
    {
        return $this->activeEngagementScope($query, 'reactions');
    }
}
