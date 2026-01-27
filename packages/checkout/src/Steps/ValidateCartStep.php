<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;

final class ValidateCartStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly CartManagerInterface $cartManager,
    ) {}

    public function getIdentifier(): string
    {
        return 'validate_cart';
    }

    public function getName(): string
    {
        return 'Validate Cart';
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        $cart = $this->cartManager->getById($session->cart_id);

        if ($cart === null) {
            $errors['cart'] = 'Cart not found';

            return $errors;
        }

        if ($cart->isEmpty()) {
            $errors['cart'] = 'Cart is empty';
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $cart = $this->cartManager->getById($session->cart_id);

        if ($cart === null) {
            return $this->failed('Cart not found', ['cart' => 'Cart not found']);
        }

        if ($cart->isEmpty()) {
            return $this->failed('Cart is empty', ['cart' => 'Cart is empty']);
        }

        // Re-capture cart snapshot with latest data
        $snapshot = [
            'items' => $cart->getItems()->toArray(),
            'subtotal' => $cart->subtotal()->getAmount(),
            'total' => $cart->total()->getAmount(),
            'item_count' => $cart->countItems(),
            'captured_at' => now()->toIso8601String(),
        ];

        $session->update([
            'cart_snapshot' => $snapshot,
            'subtotal' => $cart->subtotal()->getAmount(),
        ]);

        return $this->success('Cart validated successfully', [
            'item_count' => $cart->countItems(),
            'subtotal' => $cart->subtotal()->getAmount(),
        ]);
    }
}
