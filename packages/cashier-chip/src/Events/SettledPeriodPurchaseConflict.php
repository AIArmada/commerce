<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CashierChip\Subscription\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettledPeriodPurchaseConflict
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The subscription whose billing period was already settled.
     */
    public Subscription $subscription;

    /**
     * The second distinct purchase that was rolled back.
     */
    public string $purchaseId;

    /**
     * The settled billing-period key, when known.
     */
    public ?string $periodKey;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, string $purchaseId, ?string $periodKey = null)
    {
        $this->subscription = $subscription;
        $this->purchaseId = $purchaseId;
        $this->periodKey = $periodKey;
    }
}
