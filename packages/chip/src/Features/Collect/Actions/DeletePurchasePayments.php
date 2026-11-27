<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Features\Collect\Models\Purchase;

final class DeletePurchasePayments
{
    public function handle(Purchase $purchase): void
    {
        $purchase->payments()->delete();
    }
}
