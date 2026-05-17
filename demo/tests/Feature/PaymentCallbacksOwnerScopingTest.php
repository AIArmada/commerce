<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 for payment callback pages belonging to another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $orderA = OwnerContext::withOwner($ownerA, function () use ($ownerA): Order {
        return Order::create([
            'order_number' => 'ORD-OWNER-A-PAYMENT',
            'status' => Created::class,
            'subtotal' => 10_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10_000,
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'metadata' => [
                'chip_purchase_id' => 'chip-demo-purchase-id',
            ],
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerA, $ownerB, $orderA): void {
        $this->withSession(['demo_owner_id' => (string) $ownerB->getKey()])
            ->get(route('shop.payment.success', $orderA))
            ->assertNotFound();

        $this->withSession(['demo_owner_id' => (string) $ownerB->getKey()])
            ->get(route('shop.payment.failed', $orderA))
            ->assertNotFound();

        $this->withSession(['demo_owner_id' => (string) $ownerB->getKey()])
            ->get(route('shop.payment.cancelled', $orderA))
            ->assertNotFound();

        $this->withSession(['demo_owner_id' => (string) $ownerA->getKey()])
            ->get(route('shop.payment.success', $orderA))
            ->assertOk();
    });
});

it('returns 404 and does not mutate status for another owner\'s payment callbacks', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $orderA = OwnerContext::withOwner($ownerA, function () use ($ownerA): Order {
        return Order::create([
            'order_number' => 'ORD-OWNER-A-PAYMENT-MUTATION',
            'status' => Created::class,
            'subtotal' => 12_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 12_000,
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'metadata' => [
                'chip_purchase_id' => 'chip-demo-purchase-id',
            ],
        ]);
    });

    $initialStatus = (string) $orderA->status;

    OwnerContext::withOwner($ownerB, function () use ($ownerB, $orderA): void {
        $this->withSession(['demo_owner_id' => (string) $ownerB->getKey()])
            ->post(route('demo.simulate-payment', $orderA))
            ->assertNotFound();

        $this->withSession(['demo_owner_id' => (string) $ownerB->getKey()])
            ->get(route('shop.payment.failed', $orderA))
            ->assertNotFound();

        $this->withSession(['demo_owner_id' => (string) $ownerB->getKey()])
            ->get(route('shop.payment.cancelled', $orderA))
            ->assertNotFound();
    });

    $orderA->refresh();

    expect((string) $orderA->status)->toBe($initialStatus);
});
