<?php

declare(strict_types=1);

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\ConditionResource;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('cart.owner.enabled', true);
    config()->set('cart.owner.include_global', false);
});

it('rejects badge reads without a resolved owner context', function (string $resource): void {
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    expect(fn () => $resource::getNavigationBadge())->toThrow(NoCurrentOwnerException::class);
})->with([
    CartResource::class,
    CartItemResource::class,
    ConditionResource::class,
]);

it('separates badge cache entries by resolved scope and include-global mode', function (): void {
    $owner = User::factory()->create();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    OwnerContext::withOwner($owner, fn () => CartSnapshot::query()->create([
        'identifier' => 'tenant-badge-cart',
        'instance' => 'default',
    ]));
    OwnerContext::withOwner(null, fn () => CartSnapshot::query()->create([
        'identifier' => 'global-badge-cart',
        'instance' => 'default',
    ]));

    expect(CartResource::getNavigationBadge())->toBe('1');

    config()->set('cart.owner.include_global', true);

    expect(CartResource::getNavigationBadge())->toBe('2');

    $globalBadge = OwnerContext::withOwner(null, fn () => CartResource::getNavigationBadge());

    expect($globalBadge)->toBe('1');
});

it('authorizes dashboard badge reads and scopes warm cache entries', function (): void {
    $owner = User::factory()->create();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    OwnerContext::withOwner($owner, fn () => CartSnapshot::query()->create([
        'identifier' => 'tenant-abandoned-cart',
        'instance' => 'default',
        'checkout_abandoned_at' => now()->subHour(),
    ]));
    OwnerContext::withOwner(null, fn () => CartSnapshot::query()->create([
        'identifier' => 'global-abandoned-cart',
        'instance' => 'default',
        'checkout_abandoned_at' => now()->subHour(),
    ]));

    expect(CartDashboard::getNavigationBadge())->toBe('1');

    config()->set('cart.owner.include_global', true);

    expect(CartDashboard::getNavigationBadge())->toBe('2');

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    expect(fn () => CartDashboard::getNavigationBadge())->toThrow(NoCurrentOwnerException::class);
});

it('authorizes stats widget cache entries and keeps owner and global scopes separate', function (): void {
    $owner = User::factory()->create();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    OwnerContext::withOwner($owner, fn () => CartSnapshot::query()->create([
        'identifier' => 'tenant-stats-cart',
        'instance' => 'default',
        'items_count' => 1,
        'quantity' => 1,
        'last_activity_at' => now(),
    ]));
    OwnerContext::withOwner(null, fn () => CartSnapshot::query()->create([
        'identifier' => 'global-stats-cart',
        'instance' => 'default',
        'items_count' => 1,
        'quantity' => 1,
        'last_activity_at' => now(),
    ]));

    $statsMethod = (new ReflectionClass(CartStatsWidget::class))->getMethod('getStats');

    expect($statsMethod->invoke(new CartStatsWidget)[0]->getValue())->toBe('1');

    config()->set('cart.owner.include_global', true);

    expect($statsMethod->invoke(new CartStatsWidget)[0]->getValue())->toBe('2');

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    expect(fn () => $statsMethod->invoke(new CartStatsWidget))->toThrow(NoCurrentOwnerException::class);
});
