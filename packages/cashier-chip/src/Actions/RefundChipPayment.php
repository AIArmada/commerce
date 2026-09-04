<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use Lorisleiva\Actions\Concerns\AsAction;

final class RefundChipPayment
{
    use AsAction;

    public function handle(string $purchaseId, ?int $amount = null): PurchaseData | PaymentData
    {
        return Cashier::chip()->refundPurchase($purchaseId, $amount);
    }
}
