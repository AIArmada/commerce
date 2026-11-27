<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionResumed
{
    use Dispatchable;

    public readonly RecordReference $subscription;

    public function __construct(RecordReference $subscription)
    {
        $this->subscription = $subscription;
    }
}
