<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cancel a purchase via CHIP payment gateway.
 */
final class CancelPurchase
{
    use AsAction;

    public function __construct(
        private readonly PurchasesApi $purchasesApi,
    ) {}

    /**
     * Cancel an existing purchase.
     */
    public function handle(string $purchaseId): PurchaseData
    {
        return $this->purchasesApi->cancel($purchaseId);
    }
}
