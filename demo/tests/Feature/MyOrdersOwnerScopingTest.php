<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only orders for the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $orderA = OwnerContext::withOwner($ownerA, function (): Order {
        $order = Order::create([
            'order_number' => 'ORD-OWNER-A-0001',
            'status' => Created::class,
            'subtotal' => 10_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10_000,
            'currency' => 'MYR',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'quantity' => 1,
            'unit_price' => 10_000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => 'MYR',
        ]);

        return $order;
    });

    $orderB = OwnerContext::withOwner($ownerB, function (): Order {
        $order = Order::create([
            'order_number' => 'ORD-OWNER-B-0001',
            'status' => Created::class,
            'subtotal' => 20_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 20_000,
            'currency' => 'MYR',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Nike Air Jordan',
            'sku' => 'AJ1-001',
            'quantity' => 1,
            'unit_price' => 20_000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => 'MYR',
        ]);

        return $order;
    });

    OwnerContext::withOwner($ownerA, function () use ($orderA, $orderB): void {
        $this->get('/my-orders')
            ->assertOk()
            ->assertSee($orderA->order_number)
            ->assertDontSee($orderB->order_number);
    });
});

it('returns 404 when viewing another owner\'s order success page', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $orderA = OwnerContext::withOwner($ownerA, function (): Order {
        $order = Order::create([
            'order_number' => 'ORD-OWNER-A-DETAILS',
            'status' => Created::class,
            'subtotal' => 30_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 30_000,
            'currency' => 'MYR',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'name' => 'MacBook Pro 14"',
            'sku' => 'MBP14-001',
            'quantity' => 1,
            'unit_price' => 30_000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'currency' => 'MYR',
        ]);

        return $order;
    });

    OwnerContext::withOwner($ownerB, function () use ($orderA): void {
        $this->get(route('shop.order.success', $orderA))
            ->assertNotFound();
    });
});
