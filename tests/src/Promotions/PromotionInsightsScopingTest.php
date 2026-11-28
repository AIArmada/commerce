<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionPerformanceInsights;

it('scopes insight aggregates to the current owner', function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('promotions.features.owner.auto_assign_on_create', true);
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, fn (): Promotion => Promotion::factory()->active()->create());
    OwnerContext::withOwner($ownerA, fn (): Promotion => Promotion::factory()->active()->create());
    OwnerContext::withOwner($ownerB, fn (): Promotion => Promotion::factory()->active()->create());

    OwnerContext::withOwner($ownerA, fn (): Order => Order::factory()->create([
        'grand_total' => 10000,
        'currency' => 'MYR',
    ]));
    OwnerContext::withOwner($ownerB, fn (): Order => Order::factory()->create([
        'grand_total' => 20000,
        'currency' => 'MYR',
    ]));

    $overviewA = OwnerContext::withOwner($ownerA, fn (): array => app(PromotionPerformanceInsights::class)->overview());

    expect($overviewA['total_promotions'])->toBe(2)
        ->and($overviewA['total_orders'])->toBe(1);
});

it('does not retain one owner order report when the same insights instance is reused', function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    foreach ([[$ownerA, 'Owner A', 10000], [$ownerB, 'Owner B', 20000]] as [$owner, $name, $total]) {
        OwnerContext::withOwner($owner, function () use ($name, $total): void {
            $promotion = Promotion::factory()->active()->create(['name' => $name, 'code' => null]);

            Order::factory()->create([
                'grand_total' => $total,
                'currency' => 'MYR',
                'metadata' => ['discount_data' => ['allocations' => [[
                    'provider_key' => 'promotions',
                    'candidate_key' => 'promotion:' . $promotion->id,
                    'label' => $name,
                    'requested_amount' => 100,
                    'meta' => ['promotion_id' => $promotion->id],
                ]]]],
            ]);
        });
    }

    $insights = app(PromotionPerformanceInsights::class);

    OwnerContext::withOwner($ownerA, function () use ($insights): void {
        expect($insights->overview()['influenced_revenue_minor'])->toBe(10000)
            ->and($insights->topPromotionsByOrders()->pluck('label')->all())->toBe(['Owner A']);
    });

    OwnerContext::withOwner($ownerB, function () use ($insights): void {
        expect($insights->overview()['influenced_revenue_minor'])->toBe(20000)
            ->and($insights->topPromotionsByOrders()->pluck('label')->all())->toBe(['Owner B']);
    });
});

it('counts only valid positive promotion allocations in order analytics', function (): void {
    $promotion = Promotion::factory()->active()->create(['name' => 'Campaign']);
    $allocation = [
        'provider_key' => 'promotions',
        'candidate_key' => 'promotion:' . $promotion->id,
        'label' => 'Campaign',
        'requested_amount' => 200,
        'meta' => ['promotion_id' => $promotion->id],
    ];

    Order::factory()->create([
        'grand_total' => 1000,
        'currency' => 'MYR',
        'metadata' => ['discount_data' => ['allocations' => [
            $allocation,
            array_replace($allocation, ['requested_amount' => 300]),
            array_replace($allocation, ['provider_key' => 'vouchers']),
            array_replace($allocation, ['requested_amount' => 0]),
            array_replace($allocation, ['meta' => []]),
        ]]],
    ]);

    Order::factory()->create([
        'grand_total' => 2000,
        'currency' => 'MYR',
        'metadata' => ['discount_data' => ['allocations' => [
            array_replace($allocation, ['provider_key' => 'vouchers']),
        ]]],
    ]);

    $insights = app(PromotionPerformanceInsights::class);
    $overview = $insights->overview();
    $top = $insights->topPromotionsByOrders()->sole();

    expect($overview['total_orders'])->toBe(2)
        ->and($overview['influenced_orders'])->toBe(1)
        ->and($overview['influenced_revenue_minor'])->toBe(1000)
        ->and($overview['attributed_discount_minor'])->toBe(500)
        ->and($top['order_count'])->toBe(1)
        ->and($top['attributed_discount_minor'])->toBe(500);
});
