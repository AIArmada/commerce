<?php

declare(strict_types=1);

namespace AIArmada\Cart\Projectors;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\ReadModels\CartReadModel;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Cart projector that updates read models based on domain events.
 *
 * Listens to cart events and updates the read model cache accordingly.
 * This enables CQRS pattern by keeping read models in sync with writes.
 */
final class CartProjector
{
    public function __construct(
        private readonly CartReadModel $readModel
    ) {}

    /**
     * Register event listeners with the dispatcher.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(CartCreated::class, [$this, 'onCartCreated']);
        $events->listen(CartDestroyed::class, [$this, 'onCartDestroyed']);
        $events->listen(CartCleared::class, [$this, 'onCartCleared']);
        $events->listen(ItemAdded::class, [$this, 'onItemAdded']);
        $events->listen(ItemRemoved::class, [$this, 'onItemRemoved']);
        $events->listen(ItemUpdated::class, [$this, 'onItemUpdated']);
        $events->listen(CartConditionAdded::class, [$this, 'onConditionAdded']);
        $events->listen(CartConditionRemoved::class, [$this, 'onConditionRemoved']);
    }

    /**
     * Handle cart created event.
     */
    public function onCartCreated(CartCreated $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle cart destroyed event.
     */
    public function onCartDestroyed(CartDestroyed $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle cart cleared event.
     */
    public function onCartCleared(CartCleared $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle item added event.
     */
    public function onItemAdded(ItemAdded $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle item removed event.
     */
    public function onItemRemoved(ItemRemoved $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle item updated event.
     */
    public function onItemUpdated(ItemUpdated $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle condition added event.
     */
    public function onConditionAdded(CartConditionAdded $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }

    /**
     * Handle condition removed event.
     */
    public function onConditionRemoved(CartConditionRemoved $event): void
    {
        $cartId = $event->getCartId();
        if ($cartId !== null) {
            $this->readModel->invalidateCache($cartId);
        }
    }
}
