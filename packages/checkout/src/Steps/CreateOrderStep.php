<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Inventory\InventoryServiceProvider;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CreateOrderStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ?VouchersAdapter $vouchersAdapter = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'create_order';
    }

    public function getName(): string
    {
        return 'Create Order';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['process_payment'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        $paymentData = $session->payment_data ?? [];
        $isFreeOrder = ($paymentData['type'] ?? null) === 'free_order';
        $paymentStatus = $paymentData['status'] ?? null;

        if (! $isFreeOrder && $paymentStatus !== PaymentStatus::Completed->value) {
            $errors['payment'] = 'Payment must be completed before creating order';
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if (! app()->bound(OrderServiceInterface::class)) {
            return $this->failed('Orders package not available');
        }

        $orderService = app(OrderServiceInterface::class);
        $customer = $session->customer;

        $shippingData = $session->shipping_data ?? [];
        $billingData = $session->billing_data ?? [];
        $paymentData = $session->payment_data ?? [];

        $orderData = [
            'customer_id' => $session->customer_id,
            'customer_type' => $customer?->getMorphClass(),
            'subtotal' => $session->subtotal,
            'discount_total' => $session->discount_total,
            'shipping_total' => $session->shipping_total,
            'tax_total' => $session->tax_total,
            'grand_total' => $session->grand_total,
            'currency' => $session->currency,
            'metadata' => [
                'checkout_session_id' => $session->id,
                'cart_id' => $session->cart_id,
                'payment_gateway' => $session->selected_payment_gateway,
                'payment_id' => $session->payment_id,
                'payment_data' => $paymentData,
                'discount_data' => $session->discount_data,
                'tax_data' => $session->tax_data,
            ],
        ];

        $items = $this->buildOrderItems($session);

        $order = $orderService->createOrder(
            orderData: $orderData,
            items: $items,
            billingAddress: $billingData ?: null,
            shippingAddress: $shippingData ?: null,
        );

        // Confirm payment if not a free order (triggers PaymentConfirmed transition → OrderPaid event)
        $isFreeOrder = ($paymentData['type'] ?? null) === 'free_order';
        if (! $isFreeOrder && config('checkout.create_order.confirm_payment', true)) {
            $this->confirmPayment($orderService, $order, $session, $paymentData);
        }

        $session->update([
            'order_id' => $order->id,
            'completed_at' => now(),
        ]);

        $this->redeemAppliedVouchers($session, $order->id);

        if ($session->status->is(Pending::class)) {
            $session->transitionStatus(Processing::class);
        }

        // Only transition to Completed if not already in that state
        if (! $session->status->is(Completed::class)) {
            $session->transitionStatus(Completed::class);
        }

        $this->commitInventoryReservations($session);
        $this->clearCart($session);

        return $this->success('Order created successfully', [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? $order->id,
        ]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildOrderItems(CheckoutSession $session): array
    {
        $cartItems = array_values($session->cart_snapshot['items'] ?? []);
        $pricingItems = array_values($session->pricing_data['items'] ?? []);

        $lineBases = [];

        foreach ($cartItems as $index => $cartItem) {
            $pricingItem = $pricingItems[$index] ?? [];
            $quantity = max(1, (int) ($pricingItem['quantity'] ?? $cartItem['quantity'] ?? 1));
            $baseUnitPrice = (int) ($pricingItem['original_unit_price'] ?? $pricingItem['unit_price'] ?? $cartItem['price'] ?? $cartItem['unit_price'] ?? 0);

            $lineBases[$index] = $baseUnitPrice * $quantity;
        }

        $checkoutDiscountAllocations = $this->allocateAmount((int) $session->discount_total, $lineBases);

        $taxBases = [];

        foreach ($cartItems as $index => $cartItem) {
            $pricingItem = $pricingItems[$index] ?? [];
            $quantity = max(1, (int) ($pricingItem['quantity'] ?? $cartItem['quantity'] ?? 1));
            $baseUnitPrice = (int) ($pricingItem['original_unit_price'] ?? $pricingItem['unit_price'] ?? $cartItem['price'] ?? $cartItem['unit_price'] ?? 0);
            $finalUnitPrice = (int) ($pricingItem['unit_price'] ?? $cartItem['price'] ?? $cartItem['unit_price'] ?? $baseUnitPrice);
            $pricingDiscount = max(0, ($baseUnitPrice - $finalUnitPrice) * $quantity);

            $taxBases[$index] = max(0, ($lineBases[$index] ?? 0) - $pricingDiscount - ($checkoutDiscountAllocations[$index] ?? 0));
        }

        $taxAllocations = $this->allocateAmount((int) $session->tax_total, $taxBases);

        return array_map(function (array $cartItem, int $index) use ($pricingItems, $checkoutDiscountAllocations, $taxAllocations): array {
            $pricingItem = $pricingItems[$index] ?? [];
            $quantity = max(1, (int) ($pricingItem['quantity'] ?? $cartItem['quantity'] ?? 1));
            $baseUnitPrice = (int) ($pricingItem['original_unit_price'] ?? $pricingItem['unit_price'] ?? $cartItem['price'] ?? $cartItem['unit_price'] ?? 0);
            $finalUnitPrice = (int) ($pricingItem['unit_price'] ?? $cartItem['price'] ?? $cartItem['unit_price'] ?? $baseUnitPrice);
            $pricingDiscount = max(0, ($baseUnitPrice - $finalUnitPrice) * $quantity);
            $associatedModel = $cartItem['associated_model'] ?? null;

            return [
                'purchasable_id' => $cartItem['product_id']
                    ?? $cartItem['purchasable_id']
                    ?? (is_array($associatedModel) ? $associatedModel['id'] ?? null : null),
                'purchasable_type' => $cartItem['purchasable_type']
                    ?? (is_array($associatedModel) ? $associatedModel['class'] ?? null : null),
                'name' => $cartItem['name'] ?? '',
                'sku' => $cartItem['sku'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $baseUnitPrice,
                'discount_amount' => $pricingDiscount + ($checkoutDiscountAllocations[$index] ?? 0),
                'tax_amount' => $taxAllocations[$index] ?? 0,
                'currency' => $cartItem['currency'] ?? null,
                'options' => $cartItem['attributes'] ?? $cartItem['options'] ?? null,
                'metadata' => array_merge(
                    $cartItem['metadata'] ?? [],
                    ['pricing_breakdown' => $pricingItem['pricing_breakdown'] ?? []],
                ),
            ];
        }, $cartItems, array_keys($cartItems));
    }

    private function commitInventoryReservations(CheckoutSession $session): void
    {
        if (! class_exists(InventoryServiceProvider::class)) {
            return;
        }

        $pricingData = $session->pricing_data ?? [];
        $reservations = $pricingData['inventory_reservations'] ?? [];

        if (empty($reservations)) {
            return;
        }

        $inventoryAdapter = app(InventoryAdapter::class);

        // Commit all reservations for this checkout session at once
        $inventoryAdapter->commitAllForReference($session->id, $session->order_id);
    }

    private function clearCart(CheckoutSession $session): void
    {
        if (! app()->bound(CartManagerInterface::class)) {
            return;
        }

        $cartManager = app(CartManagerInterface::class);
        $cart = $cartManager->getById($session->cart_id);

        if ($cart !== null) {
            $cart->clear();
        }
    }

    private function redeemAppliedVouchers(CheckoutSession $session, string $orderId): void
    {
        if ($this->vouchersAdapter === null) {
            return;
        }

        $discountData = $session->discount_data ?? [];
        $voucherCodes = array_values(array_filter(array_map(
            static fn (array $voucher): ?string => $voucher['code'] ?? null,
            $discountData['vouchers'] ?? [],
        )));

        if ($voucherCodes === []) {
            return;
        }

        $this->vouchersAdapter->redeemVouchers($voucherCodes, $orderId);
    }

    /**
     * @param  array<int, int>  $bases
     * @return array<int, int>
     */
    private function allocateAmount(int $amount, array $bases): array
    {
        $allocations = array_fill(0, count($bases), 0);
        $remainingAmount = max(0, $amount);
        $positiveIndexes = array_values(array_filter(array_keys($bases), fn (int $index): bool => ($bases[$index] ?? 0) > 0));

        if ($positiveIndexes === [] || $remainingAmount === 0) {
            return $allocations;
        }

        $remainingBase = array_sum(array_intersect_key($bases, array_flip($positiveIndexes)));

        foreach ($positiveIndexes as $position => $index) {
            if ($position === array_key_last($positiveIndexes)) {
                $allocations[$index] = $remainingAmount;

                break;
            }

            $base = $bases[$index];
            $allocated = (int) floor(($base / $remainingBase) * $remainingAmount);

            $allocations[$index] = $allocated;
            $remainingAmount -= $allocated;
            $remainingBase -= $base;
        }

        return $allocations;
    }

    /**
     * Confirm payment via order service to trigger PaymentConfirmed transition.
     *
     * This creates the Payment record on the order and dispatches OrderPaid event.
     *
     * @param  array<string, mixed>  $paymentData
     */
    private function confirmPayment(
        OrderServiceInterface $orderService,
        Order $order,
        CheckoutSession $session,
        array $paymentData,
    ): void {
        $transactionId = $paymentData['transaction_id']
            ?? $paymentData['payment_id']
            ?? $session->payment_id
            ?? 'unknown';

        $gateway = $paymentData['gateway']
            ?? $session->selected_payment_gateway
            ?? 'unknown';

        $amount = $paymentData['amount'] ?? $session->grand_total;

        $metadata = [
            'checkout_session_id' => $session->id,
            'payment_id' => $session->payment_id,
            'gateway_response' => $paymentData['gateway_response'] ?? null,
        ];

        try {
            $orderService->confirmPayment(
                order: $order,
                transactionId: $transactionId,
                gateway: $gateway,
                amount: (int) $amount,
                metadata: $metadata,
            );

            Log::debug('Payment confirmed for order via checkout', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'transaction_id' => $transactionId,
                'gateway' => $gateway,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to confirm payment for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Don't re-throw - order was created successfully, payment confirmation is supplementary
        }
    }
}
