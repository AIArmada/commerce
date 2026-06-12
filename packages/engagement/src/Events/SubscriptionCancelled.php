<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Subscription;

final class SubscriptionCancelled
{
    public function __construct(public Subscription $subscription) {}
}
