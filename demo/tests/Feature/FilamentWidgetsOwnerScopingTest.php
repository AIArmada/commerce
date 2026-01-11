<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopAffiliates;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('owner-scopes the LatestOrders widget query', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $orderA = OwnerContext::withOwner($ownerA, function (): Order {
        return Order::create([
            'order_number' => 'ORD-WIDGET-A-0001',
            'status' => Created::class,
            'subtotal' => 10_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10_000,
            'currency' => 'MYR',
        ]);
    });

    $orderB = OwnerContext::withOwner($ownerB, function (): Order {
        return Order::create([
            'order_number' => 'ORD-WIDGET-B-0001',
            'status' => Created::class,
            'subtotal' => 20_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 20_000,
            'currency' => 'MYR',
        ]);
    });

    $widget = new LatestOrders;

    $numbers = OwnerContext::withOwner($ownerA, function () use ($widget): array {
        /** @var Builder<Order> $query */
        $method = new ReflectionMethod(LatestOrders::class, 'getTableQuery');
        $method->setAccessible(true);
        $query = $method->invoke($widget);

        return $query->pluck('order_number')->all();
    });

    expect($numbers)->toContain($orderA->order_number);
    expect($numbers)->not->toContain($orderB->order_number);
});

it('owner-scopes the TopAffiliates widget query', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $affiliateA = OwnerContext::withOwner($ownerA, function (): Affiliate {
        return Affiliate::create([
            'code' => 'OWNER-A-CODE',
            'name' => 'Owner A Affiliate',
            'status' => AffiliateStatus::Active,
        ]);
    });

    $affiliateB = OwnerContext::withOwner($ownerB, function (): Affiliate {
        return Affiliate::create([
            'code' => 'OWNER-B-CODE',
            'name' => 'Owner B Affiliate',
            'status' => AffiliateStatus::Active,
        ]);
    });

    $widget = new TopAffiliates;

    $codes = OwnerContext::withOwner($ownerA, function () use ($widget): array {
        /** @var Builder<Affiliate> $query */
        $method = new ReflectionMethod(TopAffiliates::class, 'getTableQuery');
        $method->setAccessible(true);
        $query = $method->invoke($widget);

        return $query->pluck('code')->all();
    });

    expect($codes)->toContain($affiliateA->code);
    expect($codes)->not->toContain($affiliateB->code);
});

it('owner-scopes the StatsOverview widget metrics', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, function (): void {
        Product::create([
            'name' => 'Owner A Product',
            'sku' => 'OWN-A-001',
            'price' => 10_00,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);

        Order::create([
            'order_number' => 'ORD-REV-A-PAID',
            'status' => Created::class,
            'subtotal' => 10_00,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10_00,
            'currency' => 'MYR',
            'paid_at' => CarbonImmutable::now(),
        ]);

        Order::create([
            'order_number' => 'ORD-REV-A-PENDING',
            'status' => Created::class,
            'subtotal' => 12_00,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 12_00,
            'currency' => 'MYR',
            'paid_at' => null,
        ]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        Product::create([
            'name' => 'Owner B Product',
            'sku' => 'OWN-B-001',
            'price' => 20_00,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);

        Order::create([
            'order_number' => 'ORD-REV-B-PAID',
            'status' => Created::class,
            'subtotal' => 20_00,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 20_00,
            'currency' => 'MYR',
            'paid_at' => CarbonImmutable::now(),
        ]);
    });

    $widget = new StatsOverview;

    $stats = OwnerContext::withOwner($ownerA, function () use ($widget): array {
        $method = new ReflectionMethod(StatsOverview::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    });

    $byLabel = collect($stats)->keyBy(fn ($stat) => (string) $stat->getLabel());

    expect((string) $byLabel['Total Revenue']->getValue())->toContain('RM');
    expect((string) $byLabel['Total Revenue']->getValue())->toContain('10.00');
    expect((int) $byLabel['Pending Orders']->getValue())->toBe(1);
    expect((int) $byLabel['Products']->getValue())->toBe(1);
});

it('owner-scopes the RevenueChart widget dataset', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, function (): void {
        Order::create([
            'order_number' => 'ORD-CHART-A',
            'status' => Created::class,
            'subtotal' => 15_00,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 15_00,
            'currency' => 'MYR',
            'paid_at' => CarbonImmutable::now(),
        ]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        Order::create([
            'order_number' => 'ORD-CHART-B',
            'status' => Created::class,
            'subtotal' => 25_00,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 25_00,
            'currency' => 'MYR',
            'paid_at' => CarbonImmutable::now(),
        ]);
    });

    $widget = new RevenueChart;

    $data = OwnerContext::withOwner($ownerA, function () use ($widget): array {
        $method = new ReflectionMethod(RevenueChart::class, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    });

    /** @var array<int, float|int|string> $series */
    $series = $data['datasets'][0]['data'];

    // The final data point corresponds to today.
    expect((float) end($series))->toBe(15.0);
});
