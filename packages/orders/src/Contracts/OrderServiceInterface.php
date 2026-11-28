<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for order service operations.
 */
interface OrderServiceInterface
{
    /**
     * Create a new order from cart data.
     *
     * @param  array<string, mixed>  $orderData
     * @param  array<array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $billingAddress
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  string|null  $intakeSource  Deduplication identity source (e.g. 'checkout', 'api')
     * @param  string|null  $intakeId  Deduplication identity (e.g. session ID, idempotency key)
     */
    public function createOrder(
        array $orderData,
        array $items,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $intakeSource = null,
        ?string $intakeId = null,
    ): Order;

    /**
     * Create order from a cart or cart manager.
     *
     * @param  array<string, mixed>|null  $billingAddress
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  string|null  $intakeSource  Deduplication identity source
     * @param  string|null  $intakeId  Deduplication identity
     * @param  string|null  $sessionId  Checkout/session identifier for order metadata
     */
    public function createFromCart(
        Cart | CartManagerInterface $cart,
        Model $customer,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $intakeSource = null,
        ?string $intakeId = null,
        ?string $sessionId = null,
    ): Order;

    /**
     * Add an item to an order.
     *
     * @param  array<string, mixed>  $itemData
     */
    public function addItem(Order $order, array $itemData): OrderItem;

    /**
     * Add an address to an order.
     *
     * @param  array<string, mixed>  $addressData
     */
    public function addAddress(Order $order, array $addressData, string $type): void;

    /**
     * Recalculate order totals from items.
     */
    public function recalculateTotals(Order $order): Order;
}
