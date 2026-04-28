<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use InvalidArgumentException;

/**
 * Optional orders integration for affiliates.
 *
 * The affiliates core model is reference-neutral. This listener adapts an
 * orders event into the compatibility payload expected by recordConversion().
 */
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
        $reference = $order->order_number ?? $order->id;

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $cartId = $metadata['cart_id'] ?? null;

        if (! is_string($cartId) || $cartId === '') {
            return;
        }

        try {
            $owner = OwnerTupleParser::fromTypeAndId($order->owner_type, $order->owner_id)
                ->toOwnerModel();
        } catch (InvalidArgumentException $exception) {
            logger()->warning('affiliates.order_commission_skipped_malformed_owner_tuple', [
                'order_id' => $order->id,
                'order_reference' => $reference,
                'owner_type' => $order->owner_type,
                'owner_id' => $order->owner_id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        OwnerContext::withOwner($owner, function () use ($cartId, $order, $reference): void {
            $cart = $this->cartManager->getById($cartId);

            if ($cart === null || ! $cart->exists()) {
                return;
            }

            $this->affiliateService->recordConversion($cart, [
                'external_reference' => $reference,
                'order_reference' => $reference,
                'conversion_type' => 'purchase',
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
