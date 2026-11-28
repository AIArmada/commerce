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
     * Confirm payment for an order.
     *
     * Partial payments are supported: the payment is recorded with its
     * actual amount, and the order only moves to processing (and only then
     * stages fulfillment) once accepted completed payments cover the grand
     * total. Replays of a recorded payment identity stay idempotent.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function confirmPayment(
        Order $order,
        string $transactionId,
        string $gateway,
        int $amount,
        array $metadata = [],
    ): Order;

    /**
     * Confirm a free order (grand_total <= 0 and paid_total === 0) without payment.
     *
     * Moves the order to processing and triggers post-processing
     * integrations such as inventory deduction. No payment record,
     * paid timestamp, or OrderPaid event is produced.
     */
    public function confirmFreeOrder(Order $order): Order;

    /**
     * Recalculate order totals from items.
     */
    public function recalculateTotals(Order $order): Order;
}
