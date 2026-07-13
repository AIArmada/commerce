<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\Processing;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final class CheckoutFinalizer
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly ?CartManagerInterface $cartManager = null,
    ) {}

    public function finalize(CheckoutSession $session): CheckoutResult
    {
        if ($session->status->is(Completed::class)) {
            return CheckoutResult::success($session);
        }

        if (! $session->status->is(Processing::class)) {
            $session->status->transitionTo(Processing::class);
        }

        $session->status->transitionTo(Completed::class);

        $this->events->dispatch(new CheckoutCompleted($session));

        $this->clearCart($session);

        return CheckoutResult::success($session);
    }

    private function clearCart(CheckoutSession $session): void
    {
        $cartManager = $this->cartManager;

        if ($cartManager === null) {
            if (! app()->bound(CartManagerInterface::class)) {
                return;
            }

            $resolved = app(CartManagerInterface::class);

            if (! $resolved instanceof CartManagerInterface) {
                return;
            }

            $cartManager = $resolved;
        }

        try {
            $cartId = $session->getAttribute('cart_id');

            if ($cartId !== null) {
                $cart = $cartManager->getById($cartId);

                if ($cart !== null) {
                    $cart->clear();
                }
            }
        } catch (Throwable) {
            // ponytail: cart clearing failures don't roll back completed checkout
        }
    }
}
