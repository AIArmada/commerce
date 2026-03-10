<?php

declare(strict_types=1);

namespace AIArmada\Signals\Services;

use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

final class CommerceSignalsRecorder
{
    public function __construct(
        private readonly TrackedPropertyResolver $trackedPropertyResolver,
        private readonly IngestSignalEvent $ingestSignalEvent,
    ) {}

    public function recordCheckoutCompleted(Model $session): ?SignalEvent
    {
        $trackedProperty = $this->trackedPropertyResolver->resolveForModel($session);

        if ($trackedProperty === null) {
            return null;
        }

        return $this->ingestSignalEvent->handle($trackedProperty, [
            'event_name' => (string) config('signals.integrations.checkout.event_name', 'checkout.completed'),
            'event_category' => (string) config('signals.integrations.checkout.event_category', 'checkout'),
            'external_id' => $this->stringValue($session->getAttribute('customer_id')),
            'anonymous_id' => $this->stringValue($session->getAttribute('cart_id')),
            'occurred_at' => $this->timestampValue($session->getAttribute('completed_at') ?? $session->getAttribute('updated_at')),
            'revenue_minor' => (int) ($session->getAttribute('grand_total') ?? 0),
            'currency' => $this->stringValue($session->getAttribute('currency')) ?? (string) config('signals.defaults.currency', 'MYR'),
            'properties' => array_filter([
                'checkout_session_id' => $this->stringValue($session->getKey()),
                'order_id' => $this->stringValue($session->getAttribute('order_id')),
                'payment_gateway' => $this->stringValue($session->getAttribute('selected_payment_gateway')),
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    public function recordCheckoutStarted(Model $session): ?SignalEvent
    {
        $trackedProperty = $this->trackedPropertyResolver->resolveForModel($session);

        if ($trackedProperty === null) {
            return null;
        }

        return $this->ingestSignalEvent->handle($trackedProperty, [
            'event_name' => (string) config('signals.integrations.checkout.started_event_name', 'checkout.started'),
            'event_category' => (string) config('signals.integrations.checkout.event_category', 'checkout'),
            'external_id' => $this->stringValue($session->getAttribute('customer_id')),
            'anonymous_id' => $this->stringValue($session->getAttribute('cart_id')),
            'occurred_at' => $this->timestampValue($session->getAttribute('created_at') ?? $session->getAttribute('updated_at')),
            'revenue_minor' => (int) ($session->getAttribute('grand_total') ?? 0),
            'currency' => $this->stringValue($session->getAttribute('currency')) ?? (string) config('signals.defaults.currency', 'MYR'),
            'properties' => array_filter([
                'checkout_session_id' => $this->stringValue($session->getKey()),
                'payment_gateway' => $this->stringValue($session->getAttribute('selected_payment_gateway')),
                'shipping_method' => $this->stringValue($session->getAttribute('selected_shipping_method')),
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    public function recordOrderPaid(Model $order, ?string $transactionId = null, ?string $gateway = null): ?SignalEvent
    {
        $trackedProperty = $this->trackedPropertyResolver->resolveForModel($order);

        if ($trackedProperty === null) {
            return null;
        }

        return $this->ingestSignalEvent->handle($trackedProperty, [
            'event_name' => (string) config('signals.integrations.orders.event_name', 'order.paid'),
            'event_category' => (string) config('signals.integrations.orders.event_category', 'conversion'),
            'external_id' => $this->stringValue($order->getAttribute('customer_id')),
            'occurred_at' => $this->timestampValue($order->getAttribute('paid_at') ?? $order->getAttribute('updated_at')),
            'revenue_minor' => (int) ($order->getAttribute('grand_total') ?? 0),
            'currency' => $this->stringValue($order->getAttribute('currency')) ?? (string) config('signals.defaults.currency', 'MYR'),
            'properties' => array_filter([
                'order_id' => $this->stringValue($order->getKey()),
                'order_number' => $this->stringValue($order->getAttribute('order_number')),
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    public function recordCartItemAdded(object $cart, object $item): ?SignalEvent
    {
        return $this->recordCartEvent(
            cart: $cart,
            eventName: (string) config('signals.integrations.cart.item_added_event_name', 'cart.item.added'),
            properties: array_filter([
                'item_id' => $this->readPublicScalar($item, 'id'),
                'item_name' => $this->readPublicScalar($item, 'name'),
                'quantity' => $this->readPublicInt($item, 'quantity'),
                'unit_price_minor' => $this->readPublicInt($item, 'price'),
                'line_total_minor' => $this->calculateLineTotal($item),
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    public function recordCartItemRemoved(object $cart, object $item): ?SignalEvent
    {
        return $this->recordCartEvent(
            cart: $cart,
            eventName: (string) config('signals.integrations.cart.item_removed_event_name', 'cart.item.removed'),
            properties: array_filter([
                'item_id' => $this->readPublicScalar($item, 'id'),
                'item_name' => $this->readPublicScalar($item, 'name'),
                'quantity' => $this->readPublicInt($item, 'quantity'),
                'unit_price_minor' => $this->readPublicInt($item, 'price'),
                'line_total_minor' => $this->calculateLineTotal($item),
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    public function recordCartCleared(object $cart): ?SignalEvent
    {
        return $this->recordCartEvent(
            cart: $cart,
            eventName: (string) config('signals.integrations.cart.cleared_event_name', 'cart.cleared'),
        );
    }

    public function recordVoucherApplied(object $cart, object $voucher): ?SignalEvent
    {
        return $this->recordVoucherEvent(
            cart: $cart,
            voucher: $voucher,
            eventName: (string) config('signals.integrations.vouchers.applied_event_name', 'voucher.applied'),
        );
    }

    public function recordVoucherRemoved(object $cart, object $voucher): ?SignalEvent
    {
        return $this->recordVoucherEvent(
            cart: $cart,
            voucher: $voucher,
            eventName: (string) config('signals.integrations.vouchers.removed_event_name', 'voucher.removed'),
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordCartEvent(object $cart, string $eventName, array $properties = []): ?SignalEvent
    {
        $trackedProperty = $this->resolveTrackedPropertyForCart($cart);

        if ($trackedProperty === null) {
            return null;
        }

        $cartIdentifier = $this->callStringMethod($cart, 'getIdentifier');
        $instanceName = $this->callStringMethod($cart, 'instance') ?? 'default';
        $sessionIdentifier = $this->buildCartSessionIdentifier($cartIdentifier, $instanceName);

        return $this->ingestSignalEvent->handle($trackedProperty, [
            'event_name' => $eventName,
            'event_category' => (string) config('signals.integrations.cart.event_category', 'cart'),
            'anonymous_id' => $cartIdentifier,
            'session_identifier' => $sessionIdentifier,
            'occurred_at' => $this->timestampValue($this->callMethod($cart, 'getUpdatedAt') ?? $this->callMethod($cart, 'getCreatedAt')),
            'revenue_minor' => 0,
            'currency' => (string) config('signals.defaults.currency', 'MYR'),
            'properties' => array_filter(array_merge([
                'cart_id' => $this->callStringMethod($cart, 'getId'),
                'cart_identifier' => $cartIdentifier,
                'cart_instance' => $instanceName,
                'cart_total_minor' => $this->callIntMethod($cart, 'getRawTotal'),
                'total_quantity' => $this->callIntMethod($cart, 'getTotalQuantity'),
                'unique_item_count' => $this->callIntMethod($cart, 'countItems'),
            ], $properties), static fn (mixed $value): bool => $value !== null),
        ]);
    }

    private function recordVoucherEvent(object $cart, object $voucher, string $eventName): ?SignalEvent
    {
        $trackedProperty = $this->resolveTrackedPropertyForCart($cart);

        if ($trackedProperty === null) {
            return null;
        }

        $cartIdentifier = $this->callStringMethod($cart, 'getIdentifier');
        $instanceName = $this->callStringMethod($cart, 'instance') ?? 'default';
        $sessionIdentifier = $this->buildCartSessionIdentifier($cartIdentifier, $instanceName);

        return $this->ingestSignalEvent->handle($trackedProperty, [
            'event_name' => $eventName,
            'event_category' => (string) config('signals.integrations.vouchers.event_category', 'promotion'),
            'anonymous_id' => $cartIdentifier,
            'session_identifier' => $sessionIdentifier,
            'occurred_at' => $this->timestampValue($this->callMethod($cart, 'getUpdatedAt') ?? $this->callMethod($cart, 'getCreatedAt')),
            'revenue_minor' => 0,
            'currency' => $this->readPublicScalar($voucher, 'currency') ?? (string) config('signals.defaults.currency', 'MYR'),
            'properties' => array_filter([
                'cart_id' => $this->callStringMethod($cart, 'getId'),
                'cart_identifier' => $cartIdentifier,
                'cart_instance' => $instanceName,
                'cart_total_minor' => $this->callIntMethod($cart, 'getRawTotal'),
                'voucher_id' => $this->readPublicScalar($voucher, 'id'),
                'voucher_code' => $this->readPublicScalar($voucher, 'code'),
                'voucher_name' => $this->readPublicScalar($voucher, 'name'),
                'voucher_type' => $this->resolveVoucherType($voucher),
                'voucher_value' => $this->readPublicInt($voucher, 'value'),
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    private function resolveTrackedPropertyForCart(object $cart): ?TrackedProperty
    {
        $storage = $this->callMethod($cart, 'storage');

        if (! is_object($storage) || ! method_exists($storage, 'getOwnerType') || ! method_exists($storage, 'getOwnerId')) {
            return null;
        }

        $ownerType = $storage->getOwnerType();
        $ownerId = $storage->getOwnerId();

        return $this->trackedPropertyResolver->resolveForOwnerReference(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null,
        );
    }

    private function buildCartSessionIdentifier(?string $cartIdentifier, string $instanceName): ?string
    {
        if ($cartIdentifier === null || $cartIdentifier === '') {
            return null;
        }

        return 'cart:' . $instanceName . ':' . $cartIdentifier;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function timestampValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) ? $value : null;
    }

    private function callMethod(object $object, string $method): mixed
    {
        if (! method_exists($object, $method)) {
            return null;
        }

        return $object->{$method}();
    }

    private function callStringMethod(object $object, string $method): ?string
    {
        return $this->stringValue($this->callMethod($object, $method));
    }

    private function callIntMethod(object $object, string $method): ?int
    {
        $value = $this->callMethod($object, $method);

        return is_int($value) ? $value : null;
    }

    private function readPublicScalar(object $object, string $property): ?string
    {
        if (! property_exists($object, $property)) {
            return null;
        }

        $value = $object->{$property};

        return is_scalar($value) ? (string) $value : null;
    }

    private function readPublicInt(object $object, string $property): ?int
    {
        if (! property_exists($object, $property)) {
            return null;
        }

        $value = $object->{$property};

        return is_int($value) ? $value : null;
    }

    private function calculateLineTotal(object $item): ?int
    {
        $price = $this->readPublicInt($item, 'price');
        $quantity = $this->readPublicInt($item, 'quantity');

        if ($price === null || $quantity === null) {
            return null;
        }

        return $price * $quantity;
    }

    private function resolveVoucherType(object $voucher): ?string
    {
        if (! property_exists($voucher, 'type')) {
            return null;
        }

        $type = $voucher->type;

        if (is_object($type) && property_exists($type, 'value') && is_scalar($type->value)) {
            return (string) $type->value;
        }

        return is_scalar($type) ? (string) $type : null;
    }
}
