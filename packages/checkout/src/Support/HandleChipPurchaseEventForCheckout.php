<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;

final class HandleChipPurchaseEventForCheckout
{
    public function __construct(
        private readonly ProcessCheckoutPaymentNotification $processCheckoutPaymentNotification,
    ) {}

    public function handle(PurchasePaid | PurchasePaymentFailure | PurchaseCancelled $event): void
    {
        $callbackType = match (true) {
            $event instanceof PurchasePaid => 'success',
            $event instanceof PurchasePaymentFailure => 'failure',
            $event instanceof PurchaseCancelled => 'cancel',
        };

        $this->processCheckoutPaymentNotification->handle(
            payload: $event->payload,
            callbackType: $callbackType,
            context: [
                'source' => 'chip.event',
                'event_type' => $event->getEventTypeValue(),
                'purchase_id' => $event->getPurchaseId(),
            ],
            expectedGateways: ['chip', 'cashier-chip'],
        );
    }
}
