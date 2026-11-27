<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionCreated
{
    use Dispatchable;

    /**
     * The subscription instance.
     */
    public readonly RecordReference $subscription;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(RecordReference $subscription)
    {
        $this->subscription = $subscription;
    }
}
