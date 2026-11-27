<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionRenewed
{
    use Dispatchable;

    /**
     * The subscription instance.
     */
    public readonly RecordReference $subscription;

    /**
     * The gateway purchase identifier, null for zero-amount renewals.
     */
    public readonly ?string $purchaseId;

    /**
     * Create a new event instance.
     */
    public function __construct(RecordReference $subscription, ?string $purchaseId = null)
    {
        $this->subscription = $subscription;
        $this->purchaseId = $purchaseId;
    }
}
