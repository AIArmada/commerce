<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Api;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Subscribable;

interface WritesSubscriptionRecords
{
    /** @return array<string, mixed> */
    public function recordSubscription(CanInteract $subscriber, ?Subscribable $subject = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): array;
}
