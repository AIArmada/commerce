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
