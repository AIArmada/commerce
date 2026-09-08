<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasSubscriptions
{
    use ReceivesEngagement;

    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->engagementRelation(Subscription::class, 'subscribable');
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeActiveSubscriptions(Builder $query): Builder
    {
        return $this->activeEngagementScope($query, 'subscriptions');
    }
}
