<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CompositeCartRevalidator;
use Carbon\CarbonImmutable;

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

        foreach ($cart->validateContextualBuyables() as $lineId => $message) {
            $errors['purchase_context.' . $lineId] = $message;
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

        $contextErrors = $cart->validateContextualBuyables();

        if ($contextErrors !== []) {
            return $this->failed('Purchase context validation failed', $contextErrors);
        }

        if (CompositeCartRevalidator::supports($session)) {
            return $this->success('Composite cart snapshot retained for payment review');
        }

        $snapshot = $cart->content();

        $totals = [
            'subtotal' => (int) ($snapshot['subtotal'] ?? $cart->subtotal()->getAmount()),
            'total' => (int) ($snapshot['total'] ?? $cart->total()->getAmount()),
        ];

        $totals['subtotal_without_conditions'] = (int) $cart->getRawSubtotalWithoutConditions();

        $snapshot['item_count'] = $snapshot['item_count'] ?? $snapshot['count'] ?? $cart->countItems();
        $snapshot['totals'] = $totals;
        $snapshot['captured_at'] = CarbonImmutable::now()->toIso8601String();

        $session->persistState([
            'cart_snapshot' => $snapshot,
            'subtotal' => $totals['subtotal'],
        ]);

        return $this->success('Cart validated successfully', [
            'item_count' => $cart->countItems(),
            'subtotal' => $cart->subtotal()->getAmount(),
        ]);
    }
}
