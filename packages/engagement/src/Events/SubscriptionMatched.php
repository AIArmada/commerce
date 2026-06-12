<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Subscription;

final class SubscriptionMatched
{
    public function __construct(public Subscription $subscription, public mixed $subject, public string $trigger) {}
}
