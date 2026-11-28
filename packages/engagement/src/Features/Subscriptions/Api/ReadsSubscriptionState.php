<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Api;

interface ReadsSubscriptionState
{
    public function subscriptionsFor(mixed $subscriber, mixed $subject = null): array;
}
