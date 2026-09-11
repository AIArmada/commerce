<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;

describe('OrderItem Model', function (): void {
    describe('OrderItem Creation', function (): void {
        it('can create an order item', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Test Product',
                'sku' => 'SKU-001',
                'quantity' => 2,
                'unit_price' => 2500,
                'total' => 5000,
            ]);

            expect($item)->toBeInstanceOf(OrderItem::class)
                ->and($item->name)->toBe('Test Product')
                ->and($item->quantity)->toBe(2);
        });

        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 3000,
                'grand_total' => 3000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Product X',
                'quantity' => 1,
                'unit_price' => 3000,
                'total' => 3000,
            ]);

            expect($item->order->id)->toBe($order->id);
        });
    });

    describe('OrderItem Calculations', function (): void {
        it('can calculate total with discount', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 4500,
                'grand_total' => 4500,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Discounted Product',
                'quantity' => 2,
                'unit_price' => 2500,
                'discount_amount' => 500,
                'total' => 4500,
            ]);

            expect($item->unit_price)->toBe(2500)
                ->and($item->discount_amount)->toBe(500)
                ->and($item->total)->toBe(4500);
        });

        it('can store metadata', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 2000,
                'grand_total' => 2000,
            ]);

            $metadata = ['color' => 'red', 'size' => 'L'];

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Custom Product',
                'quantity' => 1,
                'unit_price' => 2000,
                'total' => 2000,
                'metadata' => $metadata,
            ]);

            expect($item->metadata)->toBe($metadata);
        });

        it('can format unit price', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM5-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Formatted Product',
                'quantity' => 1,
                'unit_price' => 10000,
                'currency' => 'MYR',
            ]);

            expect($item->getFormattedUnitPrice())->toBe('RM100.00');
        });

        it('can format total', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM6-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Total Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'USD',
            ]);

            expect($item->getFormattedTotal())->toBe('$50.00');
        });

        it('can format with unknown currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM7-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Unknown Currency Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'XYZ',
            ]);

            expect($item->getFormattedTotal())->toBe('XYZ 50.00');
        });

        it('can format with EUR currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM8-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'EUR Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'EUR',
            ]);

            expect($item->getFormattedTotal())->toBe('€50.00');
        });

        it('can format with GBP currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM9-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'GBP Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'GBP',
            ]);

            expect($item->getFormattedTotal())->toBe('£50.00');
        });

        it('can calculate total correctly', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM7-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 9000,
                'grand_total' => 9000,
            ]);

            $item = new OrderItem([
                'order_id' => $order->id,
                'name' => 'Calc Product',
                'quantity' => 2,
                'unit_price' => 5000, // 10000 subtotal
                'discount_amount' => 1000, // 9000 after discount
                'tax_amount' => 1000, // 10000 final total
            ]);

            expect($item->calculateTotal())->toBe(10000); // (2*5000) - 1000 + 1000
        });
    });
});

describe('Order canonical addresses', function (): void {
    it('can attach and read a primary shipping address', function (): void {
        $order = createOrdersAddressTestOrder('shipping');
        $address = Address::create([
            'line1' => '123 Ship Street',
            'city' => 'Kuala Lumpur',
            'state' => 'KL',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $pivot = $order->attachAddress($address, type: 'shipping', isPrimary: true);
        $primary = $order->primaryAddress('shipping');

        expect($pivot->type)->toBe('shipping')
            ->and($order->addresses()->whereKey($address->id)->exists())->toBeTrue()
            ->and($primary)->not->toBeNull()
            ->and($primary?->city)->toBe('Kuala Lumpur');
    });

    it('can attach billing and shipping addresses independently', function (): void {
        $order = createOrdersAddressTestOrder('types');
        $billing = Address::create([
            'line1' => 'Bill Address',
            'city' => 'PJ',
            'postcode' => '47500',
            'country_code' => 'MY',
        ]);
        $shipping = Address::create([
            'line1' => 'Ship Address',
            'city' => 'KL',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $order->attachAddress($billing, type: 'billing', isPrimary: true);
        $order->attachAddress($shipping, type: 'shipping', isPrimary: true);

        expect($order->addresses)->toHaveCount(2)
            ->and($order->addressesOfType('billing')->pluck('id')->all())->toContain($billing->id)
            ->and($order->addressesOfType('shipping')->pluck('id')->all())->toContain($shipping->id);
    });

    it('promotes only the newest primary address for a type', function (): void {
        $order = createOrdersAddressTestOrder('primary');
        $first = Address::create(['line1' => 'First Street', 'country_code' => 'MY']);
        $second = Address::create(['line1' => 'Second Street', 'country_code' => 'MY']);

        $order->attachAddress($first, type: 'shipping', isPrimary: true);
        $order->attachAddress($second, type: 'shipping', isPrimary: true);

        expect($order->primaryAddress('shipping')?->id)->toBe($second->id)
            ->and($order->addresses()->whereKey($first->id)->first()?->pivot->is_primary)->toBeFalse()
            ->and($order->addresses()->whereKey($second->id)->first()?->pivot->is_primary)->toBeTrue();
    });

    it('preserves order contact fields in canonical address metadata', function (): void {
        $order = createOrdersAddressTestOrder('metadata');
        $address = Address::create([
            'line1' => '123 Main Street',
            'country_code' => 'MY',
            'metadata' => [
                Order::ADDRESS_CONTACT_METADATA_KEY => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'company' => 'ACME Corp',
                    'phone' => '0123456789',
                    'email' => 'john@example.com',
                ],
            ],
        ]);

        $order->attachAddress($address, type: 'billing', isPrimary: true);
        $contact = data_get($order->primaryAddress('billing')?->metadata, Order::ADDRESS_CONTACT_METADATA_KEY);

        expect($contact)->toMatchArray([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company' => 'ACME Corp',
            'phone' => '0123456789',
            'email' => 'john@example.com',
        ]);
    });

    it('reads canonical formatted address fields without an order address row', function (): void {
        $order = createOrdersAddressTestOrder('formatted');
        $address = Address::create([
            'line1' => '123 Main Street',
            'line2' => 'Floor 5',
            'city' => 'Kuala Lumpur',
            'state' => 'KL',
            'postcode' => '50000',
            'country_code' => 'MY',
            'formatted_address' => '123 Main Street, Floor 5, Kuala Lumpur, KL 50000, MY',
        ]);

        $order->attachAddress($address, type: 'shipping', isPrimary: true);
        $primary = $order->primaryAddress('shipping');

        expect($primary?->line1)->toBe('123 Main Street')
            ->and($primary?->line2)->toBe('Floor 5')
            ->and($primary?->formatted_address)->toBe('123 Main Street, Floor 5, Kuala Lumpur, KL 50000, MY');
    });

    it('returns no default address when the order has no attachments', function (): void {
        $order = createOrdersAddressTestOrder('addressless');

        expect($order->addresses)->toBeEmpty()
            ->and($order->primaryAddress('billing'))->toBeNull()
            ->and($order->primaryAddress('shipping'))->toBeNull();
    });
});

function createOrdersAddressTestOrder(string $suffix): Order
{
    return Order::create([
        'order_number' => 'ORD-ADDR-' . $suffix . '-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 5000,
        'grand_total' => 5000,
    ]);
}
