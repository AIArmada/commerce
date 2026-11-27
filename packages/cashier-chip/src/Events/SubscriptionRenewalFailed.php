<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionRenewalFailed
{
    use Dispatchable;

    /**
     * The subscription instance.
     */
    public readonly RecordReference $subscription;

    /**
     * The failure reason.
     */
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(RecordReference $subscription, string $reason = '')
    {
        $this->subscription = $subscription;
        $this->reason = $reason;
    }
}
