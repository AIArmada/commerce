<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Support\CompositeCartRevalidator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
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
            $session->transitionStatus(Processing::class);
        }

        $session->transitionStatus(Completed::class);

        $this->events->dispatch(new CheckoutCompleted($session));

        // Clear the cart only once checkout completion is durable.
        $cartId = $session->getAttribute('cart_id');

        if (is_string($cartId) && $cartId !== '') {
            $version = $session->cart_snapshot['version'] ?? null;
            if (! CompositeCartRevalidator::supports($session)) {
                DB::afterCommit(fn () => $this->clearCart($cartId, null));
            } elseif (is_int($version)) {
                $revision = CartRevision::of($cartId, $version);
                DB::afterCommit(fn () => $this->clearCart($cartId, $revision));
            }
        }

        return CheckoutResult::success($session);
    }

    private function clearCart(string $cartId, ?CartRevision $revision): void
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
            $cart = $cartManager->getById($cartId);

            if ($revision !== null) {
                $cart?->clearIfUnchanged($revision);
            } else {
                $cart?->clear();
            }
        } catch (Throwable) {
            // ponytail: cart clearing failures don't roll back completed checkout
        }
    }
}
