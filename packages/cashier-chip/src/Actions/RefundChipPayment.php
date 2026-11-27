<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class RefundChipPayment
{
    use AsAction;

    public function handle(string $purchaseId, ?int $amount = null): PurchaseData | PaymentData
    {
        if ($amount !== null) {
            Cashier::assertAmountWithinBounds($amount);
        }

        $purchase = Cashier::chip()->getPurchase($purchaseId);

        if (! $purchase->isPaid() || ! $purchase->canBeRefunded()) {
            throw new InvalidArgumentException('The purchase is not in a refundable state.');
        }

        if ($amount !== null && $amount > $purchase->getAmountInCents()) {
            throw new InvalidArgumentException('Refund amount cannot exceed the purchase total.');
        }

        if ((bool) config('chip.owner.enabled', false)) {
            $owned = Purchase::query()
                ->forOwner(OwnerContext::CURRENT, false)
                ->whereKey($purchaseId)
                ->exists();

            if (! $owned) {
                throw new AuthorizationException('Purchase is not accessible in the current owner scope.');
            }
        }

        return Cashier::chip()->refundPurchase($purchaseId, $amount);
    }
}
