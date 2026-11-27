<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Foundation\Events\Dispatchable;

final class SettledPeriodPurchaseConflict
{
    use Dispatchable;

    /**
     * The subscription whose paid purchase requires reconciliation.
     */
    public readonly RecordReference $subscription;

    /**
     * The paid purchase that could not settle the subscription period.
     */
    public string $purchaseId;

    /**
     * The billing-period key, when known.
     */
    public ?string $periodKey;

    /**
     * The expected renewal amount in minor units, for amount-mismatch reconciliation.
     */
    public ?int $expectedAmountMinor;

    /**
     * The actual gateway-charged amount in minor units, for amount-mismatch reconciliation.
     */
    public ?int $actualAmountMinor;

    /**
     * The expected billing currency, for currency-mismatch reconciliation.
     */
    public ?string $expectedCurrency;

    /**
     * The actual gateway-charged currency, for currency-mismatch reconciliation.
     */
    public ?string $actualCurrency;

    /**
     * Create a new event instance.
     */
    public function __construct(
        RecordReference $subscription,
        string $purchaseId,
        ?string $periodKey = null,
        ?int $expectedAmountMinor = null,
        ?int $actualAmountMinor = null,
        ?string $expectedCurrency = null,
        ?string $actualCurrency = null,
    ) {
        $this->subscription = $subscription;
        $this->purchaseId = $purchaseId;
        $this->periodKey = $periodKey;
        $this->expectedAmountMinor = $expectedAmountMinor;
        $this->actualAmountMinor = $actualAmountMinor;
        $this->expectedCurrency = $expectedCurrency;
        $this->actualCurrency = $actualCurrency;
    }
}
