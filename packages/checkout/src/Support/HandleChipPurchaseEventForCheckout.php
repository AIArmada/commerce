<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\Chip\Events\PurchaseEvent;

final class HandleChipPurchaseEventForCheckout
{
    public function __construct(
        private readonly ProcessCheckoutPaymentNotification $processCheckoutPaymentNotification,
    ) {}

    public function handle(PurchaseEvent $event): void
    {
        $callbackType = match ($event->getEventTypeValue()) {
            'purchase.paid' => 'success',
            'purchase.payment_failure' => 'failure',
            'purchase.cancelled' => 'cancel',
            default => null,
        };

        if ($callbackType === null) {
            return;
        }

        $this->processCheckoutPaymentNotification->handle(
            payload: $this->normalizePayload($event->payload),
            callbackType: $callbackType,
            context: [
                'source' => 'chip.event',
                'event_type' => $event->getEventTypeValue(),
                'purchase_id' => $event->getPurchaseId(),
            ],
            expectedGateways: ['chip', 'cashier-chip'],
        );
    }

    /**
     * CHIP events expose the provider reference unchanged. Normalize a UUID
     * reference at this gateway boundary; the checkout notification action
     * only accepts the namespaced checkout form.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $reference = $payload['reference'] ?? null;

        if (is_string($reference)) {
            $normalizedReference = CheckoutPaymentReference::normalizeChipReference($reference);

            if ($normalizedReference !== null) {
                $payload['reference'] = $normalizedReference;
            }
        }

        return $payload;
    }
}
