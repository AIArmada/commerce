<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentOrders\Support\FilamentOrdersCache;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('forgets the global cache keys for a global order', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-10 10:00:00'));
    Cache::spy();

    FilamentOrdersCache::forgetForOrder(new Order([
        'owner_type' => null,
        'owner_id' => null,
    ]));

    Cache::shouldHaveReceived('forget')->with('filament-orders.status-distribution.global.owner-only');
    Cache::shouldHaveReceived('forget')->with('filament-orders.status-distribution.global.with-global');
    Cache::shouldHaveReceived('forget')->with('filament-orders.stats.global.owner-only.2025-01-10');
    Cache::shouldHaveReceived('forget')->with('filament-orders.stats.global.owner-only.2025-01-09');
});

it('forgets the owner-specific cache keys for an owned order', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-10 10:00:00'));
    Cache::spy();

    FilamentOrdersCache::forgetForOrder(new Order([
        'owner_type' => 'App\\Models\\Store',
        'owner_id' => 'store-123',
    ]));

    Cache::shouldHaveReceived('forget')->with('filament-orders.status-distribution.App\\Models\\Store:store-123.owner-only');
    Cache::shouldHaveReceived('forget')->with('filament-orders.status-distribution.App\\Models\\Store:store-123.with-global');
    Cache::shouldHaveReceived('forget')->with('filament-orders.stats.App\\Models\\Store:store-123.owner-only.2025-01-10');
    Cache::shouldHaveReceived('forget')->with('filament-orders.stats.App\\Models\\Store:store-123.owner-only.2025-01-09');
});

it('rejects empty-string owner payloads', function (): void {
    FilamentOrdersCache::forgetForOrder(new Order([
        'owner_type' => '',
        'owner_id' => '',
    ]));
})->throws(InvalidArgumentException::class);
