<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\FilamentOrders\Support\FilamentOrdersCache;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

it('forgets the global stats through owner-scoped cache tags', function (): void {
    Cache::spy();

    FilamentOrdersCache::forgetForOrder(new Order([
        'owner_type' => null,
        'owner_id' => null,
    ]));

    Cache::shouldHaveReceived('tags')->with(['owner:' . OwnerScopeKey::GLOBAL])->twice();
});

it('forgets the owner-specific stats through owner-scoped cache tags', function (): void {
    Cache::spy();

    FilamentOrdersCache::forgetForOrder(new Order([
        'owner_type' => 'App\\Models\\Store',
        'owner_id' => 'store-123',
    ]));

    $ownerKey = OwnerScopeKey::forTypeAndId('App\\Models\\Store', 'store-123');

    Cache::shouldHaveReceived('tags')->with(['owner:' . $ownerKey])->twice();
});

it('rejects empty-string owner payloads', function (): void {
    FilamentOrdersCache::forgetForOrder(new Order([
        'owner_type' => '',
        'owner_id' => '',
    ]));
})->throws(InvalidArgumentException::class);

it('computes dashboard statistics with one cached aggregate query', function (): void {
    config()->set('orders.owner.enabled', false);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $first = FilamentOrdersCache::rememberStats();
    $queriesAfterFirstRead = count(DB::getQueryLog());

    $second = FilamentOrdersCache::rememberStats();

    expect($queriesAfterFirstRead)->toBe(1)
        ->and(count(DB::getQueryLog()))->toBe($queriesAfterFirstRead)
        ->and($second)->toBe($first)
        ->and($first['statusCounts'])->toHaveCount(12);
});
