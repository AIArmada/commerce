<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Data\PurchaseData;

final class RefundChipPayment
{
    public function refund(string $purchaseId, ?int $amount = null): PurchaseData
    {
        return Cashier::chip()->refundPurchase($purchaseId, $amount);
    }
}
