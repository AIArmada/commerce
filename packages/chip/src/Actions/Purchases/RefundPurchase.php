<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\Purchase;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Refund a purchase via CHIP payment gateway.
 */
final class RefundPurchase
{
    use AsAction;

    public function __construct(
        private readonly PurchasesApi $purchasesApi,
    ) {}

    /**
     * Refund an existing purchase.
     *
     * @param  int|null  $amount  The amount to refund in minor units, or null for full refund
     */
    public function handle(string $purchaseId, ?int $amount = null): Purchase
    {
        return $this->purchasesApi->refund($purchaseId, $amount);
    }
}
