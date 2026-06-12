<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanSubscribe
{
    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    public function subscribe(mixed $subject = null, string $type = 'updates', array $criteria = [], array $options = []): Subscription
    {
        return app(SubscriptionManager::class)->subscribe($this, $subject, $type, $criteria, $options);
    }

    public function unsubscribe(mixed $subject = null, string $type = 'updates'): void
    {
        app(SubscriptionManager::class)->unsubscribe($this, $subject, $type);
    }
}
