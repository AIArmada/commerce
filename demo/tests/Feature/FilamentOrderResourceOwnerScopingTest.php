<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use App\Filament\Resources\OrderResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('owner-scopes the OrderResource navigation badge', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, function (): void {
        Order::create([
            'order_number' => 'ORD-DEMO-A-0001',
            'status' => Created::class,
            'subtotal' => 10_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 10_00,
            'currency' => 'MYR',
        ]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        Order::create([
            'order_number' => 'ORD-DEMO-B-0001',
            'status' => Created::class,
            'subtotal' => 20_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 20_00,
            'currency' => 'MYR',
        ]);
    });

    $badgeA = OwnerContext::withOwner($ownerA, fn (): ?string => OrderResource::getNavigationBadge());
    $badgeB = OwnerContext::withOwner($ownerB, fn (): ?string => OrderResource::getNavigationBadge());

    expect($badgeA)->toBe('1');
    expect($badgeB)->toBe('1');
});

it('owner-scopes the OrderResource list query', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $orderA = OwnerContext::withOwner($ownerA, function (): Order {
        return Order::create([
            'order_number' => 'ORD-DEMO-A-0001',
            'status' => Created::class,
            'subtotal' => 10_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 10_00,
            'currency' => 'MYR',
        ]);
    });

    $orderB = OwnerContext::withOwner($ownerB, function (): Order {
        return Order::create([
            'order_number' => 'ORD-DEMO-B-0001',
            'status' => Created::class,
            'subtotal' => 20_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 20_00,
            'currency' => 'MYR',
        ]);
    });

    $idsForA = OwnerContext::withOwner($ownerA, fn (): array => OrderResource::getEloquentQuery()->pluck('id')->all());

    expect($idsForA)->toContain($orderA->id);
    expect($idsForA)->not->toContain($orderB->id);
});

it('fails closed when no owner is resolved for OrderResource', function (): void {
    $badge = OrderResource::getNavigationBadge();
    $count = OrderResource::getEloquentQuery()->count();

    expect($badge)->toBeNull();
    expect($count)->toBe(0);
});
