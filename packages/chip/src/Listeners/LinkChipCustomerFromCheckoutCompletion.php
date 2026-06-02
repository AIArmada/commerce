<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Actions\LinkChipCustomerFromCheckout;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class LinkChipCustomerFromCheckoutCompletion
{
    public function __construct(
        private readonly LinkChipCustomerFromCheckout $linkChipCustomerFromCheckout,
    ) {}

    public function handle(object $event): void
    {
        $session = $event->session ?? null;

        if (! $session instanceof Model) {
            return;
        }

        if ($session->getAttribute('selected_payment_gateway') !== 'chip') {
            return;
        }

        $purchaseId = $session->getAttribute('payment_id');

        if (! is_string($purchaseId) || $purchaseId === '') {
            return;
        }

        $paymentData = $session->getAttribute('payment_data');

        if (! is_array($paymentData)) {
            return;
        }

        $payload = data_get($paymentData, 'gateway_response');

        if (! is_array($payload)) {
            return;
        }

        $this->handleWithinSessionOwnerContext($session, function () use ($payload, $session): void {
            $this->linkChipCustomerFromCheckout->handleForCheckoutSession(
                checkoutSession: $session,
                payload: $payload,
                source: 'checkout_completed',
            );
        });
    }

    /**
     * @param  callable(): void  $callback
     */
    private function handleWithinSessionOwnerContext(Model $session, callable $callback): void
    {
        if (! method_exists($session, 'hasOwner') || ! $session->hasOwner()) {
            $callback();

            return;
        }

        $owner = $session->getRelationValue('owner');

        if (! $owner instanceof Model) {
            return;
        }

        OwnerContext::withOwner($owner, $callback);
    }
}
