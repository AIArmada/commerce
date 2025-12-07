<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\Purchase;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Capture a pre-authorized purchase via CHIP payment gateway.
 */
final class CapturePurchase
{
    use AsAction;

    public function __construct(
        private readonly PurchasesApi $purchasesApi,
    ) {}

    /**
     * Capture a pre-authorized purchase.
     *
     * @param  int|null  $amount  The amount to capture in minor units, or null for full capture
     */
    public function handle(string $purchaseId, ?int $amount = null): Purchase
    {
        return $this->purchasesApi->capture($purchaseId, $amount);
    }
}
