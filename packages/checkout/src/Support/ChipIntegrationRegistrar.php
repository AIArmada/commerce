<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use Illuminate\Support\Facades\Event;

final class ChipIntegrationRegistrar
{
    public function register(): void
    {
        if (! $this->isChipPackageInstalled()) {
            return;
        }

        if (! config('checkout.integrations.chip.enabled', true)) {
            return;
        }

        Event::listen(PurchasePaid::class, HandleChipPurchaseEventForCheckout::class);
        Event::listen(PurchasePaymentFailure::class, HandleChipPurchaseEventForCheckout::class);
        Event::listen(PurchaseCancelled::class, HandleChipPurchaseEventForCheckout::class);
    }

    private function isChipPackageInstalled(): bool
    {
        return class_exists(PurchasePaid::class)
            && class_exists(PurchasePaymentFailure::class)
            && class_exists(PurchaseCancelled::class);
    }
}
