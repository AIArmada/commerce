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
        $customer = $session->customer ?? $session->billable;

        $shippingData = $session->shipping_data ?? [];
        $billingData = $session->billing_data ?? [];
        $paymentData = $session->payment_data ?? [];

        $orderData = [
            'customer_id' => $customer?->getKey(),
            'customer_type' => $customer?->getMorphClass(),
            'subtotal' => $session->subtotal,
            'discount_total' => $session->discount_total,
            'shipping_total' => $session->shipping_total,
            'tax_total' => $session->tax_total,
            'grand_total' => $session->grand_total,
            'currency' => $session->currency,
            'metadata' => $this->buildOrderMetadata($session, $paymentData),
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
        $paymentConfirmationEnabled = ! $isFreeOrder && config('checkout.create_order.confirm_payment', true);
        $paymentWasConfirmed = false;

        if ($paymentConfirmationEnabled) {
            $paymentWasConfirmed = $this->confirmPayment($orderService, $order, $session, $paymentData);
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

        if ($this->shouldCommitInventoryReservations($isFreeOrder, $paymentConfirmationEnabled, $paymentWasConfirmed)) {
            $this->commitInventoryReservations($session);
        }
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
            $attributes = is_array($cartItem['attributes'] ?? null) ? $cartItem['attributes'] : [];
            $purchasableId = $cartItem['purchasable_id']
                ?? $attributes['purchasable_id']
                ?? $cartItem['product_id']
                ?? $attributes['product_id']
                ?? (is_array($associatedModel) ? $associatedModel['id'] ?? null : null);
            $purchasableType = $cartItem['purchasable_type']
                ?? $attributes['purchasable_type']
                ?? (is_array($associatedModel) ? $associatedModel['class'] ?? null : null);
            $sku = $cartItem['sku']
                ?? $attributes['sku']
                ?? (is_array($associatedModel) ? data_get($associatedModel, 'data.sku') : null);
            $currency = $cartItem['currency'] ?? $attributes['currency'] ?? null;

            return [
                'purchasable_id' => $purchasableId,
                'purchasable_type' => $purchasableType,
                'name' => $cartItem['name'] ?? '',
                'sku' => $sku,
                'quantity' => $quantity,
                'unit_price' => $baseUnitPrice,
                'discount_amount' => $pricingDiscount + ($checkoutDiscountAllocations[$index] ?? 0),
                'tax_amount' => $taxAllocations[$index] ?? 0,
                'currency' => $currency,
                'options' => $cartItem['attributes'] ?? $cartItem['options'] ?? null,
                'metadata' => array_merge(
                    $cartItem['metadata'] ?? [],
                    ['pricing_breakdown' => $pricingItem['pricing_breakdown'] ?? []],
                ),
            ];
        }, $cartItems, array_keys($cartItems));
    }

    /**
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    private function buildOrderMetadata(CheckoutSession $session, array $paymentData): array
    {
        $cartSnapshotId = $this->stringFromCartSnapshot($session, 'id') ?? $session->cart_id;
        $cartIdentifier = $this->stringFromCartSnapshot($session, 'identifier');
        $cartInstance = $this->stringFromCartSnapshot($session, 'instance');

        $metadata = [
            'checkout_session_id' => $session->id,
            'cart_id' => $session->cart_id,
            'cart_snapshot_id' => $cartSnapshotId,
            'cart_identifier' => $cartIdentifier,
            'cart_instance' => $cartInstance,
            'payment_gateway' => $session->selected_payment_gateway,
            'payment_id' => $session->payment_id,
            'payment_data' => $paymentData,
            'discount_data' => $session->discount_data,
            'tax_data' => $session->tax_data,
        ];

        $cartMetadata = data_get($session->cart_snapshot, 'metadata');

        if (! is_array($cartMetadata)) {
            return $metadata;
        }

        $affiliate = $cartMetadata['affiliate'] ?? null;

        if (is_array($affiliate)) {
            $affiliateId = $affiliate['affiliate_id'] ?? null;
            $affiliateCode = $affiliate['affiliate_code'] ?? null;

            if (is_scalar($affiliateId) && mb_trim((string) $affiliateId) !== '') {
                $metadata['affiliate_id'] = (string) $affiliateId;
            }

            if (is_string($affiliateCode) && mb_trim($affiliateCode) !== '') {
                $metadata['affiliate_code'] = mb_trim($affiliateCode);
            }
        }

        $voucherCodes = array_values(array_filter(array_map(
            static fn (mixed $code): ?string => is_string($code) && mb_trim($code) !== '' ? mb_trim($code) : null,
            is_array($cartMetadata['voucher_codes'] ?? null) ? $cartMetadata['voucher_codes'] : [],
        )));

        if ($voucherCodes !== []) {
            $metadata['voucher_codes'] = $voucherCodes;
        }

        $promoCode = $cartMetadata['promo_code'] ?? null;

        if (is_string($promoCode) && mb_trim($promoCode) !== '') {
            $metadata['promo_code'] = mb_trim($promoCode);
        }

        return $metadata;
    }

    private function stringFromCartSnapshot(CheckoutSession $session, string $key): ?string
    {
        $value = data_get($session->cart_snapshot, $key);

        if (! is_scalar($value)) {
            return null;
        }

        $resolved = mb_trim((string) $value);

        return $resolved !== '' ? $resolved : null;
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

        // Commit all reservations for this checkout cart at once
        $inventoryAdapter->commitAllForReference($session->cart_id, $session->order_id);
    }

    private function shouldCommitInventoryReservations(
        bool $isFreeOrder,
        bool $paymentConfirmationEnabled,
        bool $paymentWasConfirmed,
    ): bool {
        if ($isFreeOrder) {
            return true;
        }

        if (! $paymentConfirmationEnabled) {
            return true;
        }

        return ! $paymentWasConfirmed;
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
    ): bool {
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

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to confirm payment for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Don't re-throw - order was created successfully, payment confirmation is supplementary

            return false;
        }
    }
}
