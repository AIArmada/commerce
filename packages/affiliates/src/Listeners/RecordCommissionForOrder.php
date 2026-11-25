<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Features\Conversions\Api\CartConversionRecording;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Actions\MergeOrderMetadata;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Optional orders integration for affiliates.
 *
 * The affiliates core model is reference-neutral. This listener adapts an
 * orders event into the canonical payload expected by recordConversion().
 *
 * Cross-system winner rule (order metadata protocol, no cross-package
 * imports — keys are plain strings both sides document):
 *
 * - `network_touch_at`: ISO instant of the network touch, explicit null
 *   when the network abstained, key absent when no network ran.
 * - `engine_touch_at`: the engine attribution's `last_referred_at`; repeated
 *   referrals refresh the comparison time without changing the first-seen
 *   time. A null value means the engine has no touch. Written by this
 *   listener as an ISO instant.
 * - `attribution_winner`: `network`|`engine`, written by whoever decides.
 *
 * Newest touch wins; ties, malformed timestamps, and missing data resolve
 * to the engine (the merchant's direct relationship wins when indistinct).
 * The loser records nothing — the winner's row plus these markers are the
 * complete audit trail. Alone (no network key), the engine trivially wins.
 */
final readonly class RecordCommissionForOrder
{
    public function __construct(
        private CartConversionRecording $recordAffiliateConversion,
        private CartManagerInterface $cartManager,
        private MergeOrderMetadata $orderMetadata,
    ) {}

    public function handle(CommissionAttributionRequired $event): void
    {
        $order = $event->order;
        $metadata = $order->metadata ?? [];
        $reference = $order->order_number ?? $order->id;

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $commissionStrategy = data_get($metadata, 'payment_data.affiliate_commission.strategy');

        if ($commissionStrategy === 'registration_lines') {
            // A host may route ticket commissions through checkout's frozen
            // registration-line seam. Do not also record this order as one
            // generic cart purchase.
            return;
        }

        $networkPresent = array_key_exists('network_touch_at', $metadata);

        $cartId = $metadata['cart_id'] ?? null;

        try {
            $owner = OwnerTupleParser::fromTypeAndId($order->owner_type, $order->owner_id)
                ->toOwnerModel();
        } catch (InvalidArgumentException $exception) {
            logger()->warning('affiliates.order_commission_skipped_malformed_owner_tuple', [
                'order_id' => $order->id,
                'external_reference' => $reference,
                'owner_type' => $order->owner_type,
                'owner_id' => $order->owner_id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        OwnerContext::withOwner($owner, function () use ($cartId, $order, $reference, $metadata, $networkPresent): void {
            if (! is_string($cartId) || $cartId === '') {
                $this->writeAbstention($order, $networkPresent);

                return;
            }

            $cart = $this->cartManager->getById($cartId);

            if ($cart === null || ! $cart->exists()) {
                $this->writeAbstention($order, $networkPresent);

                return;
            }

            $engineTouch = $this->recordAffiliateConversion->attributionTouchFor($cart);

            if ($engineTouch === null) {
                $this->writeDecision($order, null, $networkPresent ? 'network' : null, 'engine_unattributed');

                return;
            }

            $networkTouch = self::parseTouch($metadata['network_touch_at'] ?? null);

            if ($networkTouch === null) {
                $this->writeDecision($order, $engineTouch, 'engine', $networkPresent ? 'network_abstained' : 'engine_alone');
                $this->record($order, $cart, $reference);

                return;
            }

            if ($engineTouch->greaterThanOrEqualTo($networkTouch)) {
                $this->writeDecision($order, $engineTouch, 'engine', 'last_touch');
                $this->record($order, $cart, $reference);

                return;
            }

            $this->writeDecision($order, $engineTouch, 'network', 'last_touch');
        });
    }

    private function record(Order $order, mixed $cart, mixed $reference): void
    {
        $this->recordAffiliateConversion->record($cart, [
            'external_reference' => $reference,
            'conversion_type' => 'purchase',
            'subtotal' => $order->subtotal,
            'total' => $order->grand_total,
            'commission_currency' => $order->currency,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'occurred_at' => $order->paid_at ?? CarbonImmutable::now(),
        ]);
    }

    private static function parseTouch(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function writeAbstention(Order $order, bool $networkPresent): void
    {
        if (! $networkPresent) {
            return;
        }

        $this->writeDecision($order, null, 'network', 'engine_unattributed');
    }

    private function writeDecision(Order $order, ?CarbonImmutable $engineTouch, ?string $winner, string $reason): void
    {
        $this->orderMetadata->handle((string) $order->getKey(), [
            'engine_touch_at' => $engineTouch?->toIso8601String(),
            'attribution_winner' => $winner,
            'attribution_decision_reason' => $reason,
        ]);
        $order->refresh();
    }
}
