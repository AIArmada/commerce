<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Charge a recurring purchase via CHIP payment gateway.
 */
final class ChargePurchase
{
    use AsAction;

    public function __construct(
        private readonly PurchasesApi $purchasesApi,
    ) {}

    /**
     * Charge a purchase using a recurring token.
     */
    public function handle(string $purchaseId, string $recurringToken): PurchaseData
    {
        return $this->purchasesApi->charge($purchaseId, $recurringToken);
    }
}
