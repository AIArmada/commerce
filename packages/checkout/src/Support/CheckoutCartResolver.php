<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Models\CheckoutSession;

final class CheckoutCartResolver
{
    /**
     * @return Cart|array<string, mixed>
     */
    public function resolveVoucherValidationContext(CheckoutSession $session): Cart | array
    {
        $cart = $this->resolveLiveCart($session);

        if ($cart instanceof Cart) {
            return $cart;
        }

        return [
            'customer_id' => $session->customer_id,
            'subtotal' => (int) $session->subtotal,
            'total' => (int) $session->subtotal,
            'currency' => $session->currency,
        ];
    }

    public function resolveLiveCart(CheckoutSession $session): ?Cart
    {
        if (! app()->bound(CartManagerInterface::class)) {
            return null;
        }

        if (! is_string($session->cart_id) || mb_trim($session->cart_id) === '') {
            return null;
        }

        $cartManager = app(CartManagerInterface::class);

        return $cartManager->getById($session->cart_id);
    }
}
