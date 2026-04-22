<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;

final class PromotionsAdapter
{
    /**
     * Apply eligible promotions to the checkout session.
     *
     * @return array{applied: array<array<string, mixed>>, discount: int}
     */
    public function applyEligiblePromotions(CheckoutSession $session): array
    {
        if (! interface_exists(PromotionServiceInterface::class) || ! class_exists(TargetingContext::class)) {
            return ['applied' => [], 'discount' => 0];
        }

        $promotionService = app(PromotionServiceInterface::class);

        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        $context = new TargetingContext(
            cart: (object) [
                'items' => $items,
                'subtotal' => $session->subtotal,
                'total' => $session->grand_total,
                'quantity' => count($items),
            ],
            user: null,
            request: null,
            metadata: [
                'customer_id' => $session->customer_id,
                'currency' => $session->currency,
                'checkout_session_id' => $session->id,
            ],
        );

        $result = $promotionService->calculateDiscounts($context, $session->subtotal);

        $applied = [];
        foreach ($result['applied'] as $promotion) {
            $applied[] = [
                'promotion_id' => $promotion->id,
                'name' => $promotion->name,
                'code' => $promotion->code ?? null,
                'type' => $promotion->discount_type ?? 'fixed',
                'discount' => $promotion->calculateDiscount($session->subtotal),
            ];
        }

        return [
            'applied' => $applied,
            'discount' => $result['discount'],
        ];
    }
}
