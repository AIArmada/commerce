<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use InvalidArgumentException;

final readonly class RecordCommissionForOrder
{
    public function __construct(
        private AffiliateService $affiliateService,
        private CartManagerInterface $cartManager,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        $order = $event->order;
        $metadata = $order->metadata ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $cartId = $metadata['cart_id'] ?? null;

        if (! is_string($cartId) || $cartId === '') {
            return;
        }

        $owner = null;

        try {
            $owner = OwnerContext::fromTypeAndId($order->owner_type, $order->owner_id);
        } catch (InvalidArgumentException) {
            $owner = null;
        }

        OwnerContext::withOwner($owner, function () use ($cartId, $order): void {
            $cart = $this->cartManager->getById($cartId);

            if ($cart === null || ! $cart->exists()) {
                return;
            }

            $this->affiliateService->recordConversion($cart, [
                'order_reference' => $order->order_number ?? $order->id,
                'subtotal' => $order->subtotal,
                'total' => $order->grand_total,
                'commission_currency' => $order->currency,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
                'occurred_at' => $order->paid_at ?? now(),
            ]);
        });
    }
}
