<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Models\Subscription;

interface SubscriptionManager
{
    public function subscribe(mixed $subscriber, mixed $subject = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): Subscription;

    public function unsubscribe(mixed $subscriber, mixed $subject = null, string $subscriptionType = 'updates', array $criteria = []): void;

    public function muteSubscription(Subscription $subscription): Subscription;

    public function unmuteSubscription(Subscription $subscription): Subscription;

    /** @return iterable<Subscription> */
    public function matchingSubscriptions(mixed $subject, string $trigger, array $context = []): iterable;
}
