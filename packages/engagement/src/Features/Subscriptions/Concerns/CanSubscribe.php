<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Concerns;

use AIArmada\Engagement\Features\Subscriptions\Models\Subscription;
use AIArmada\Engagement\Features\Subscriptions\Actions\ManageSubscriptionsAction;
use AIArmada\Engagement\Traits\InteractsWithEngagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanSubscribe
{
    use InteractsWithEngagement;

    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    public function subscribe(mixed $subject = null, string $type = 'updates', array $criteria = [], array $options = []): Subscription
    {
        return app(ManageSubscriptionsAction::class)->subscribe($this->engagementActor(), $subject, $type, $criteria, $options);
    }

    public function unsubscribe(mixed $subject = null, string $type = 'updates', array $criteria = []): void
    {
        app(ManageSubscriptionsAction::class)->unsubscribe($this->engagementActor(), $subject, $type, $criteria);
    }
}
