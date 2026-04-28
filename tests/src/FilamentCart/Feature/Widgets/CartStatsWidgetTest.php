<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use Akaunting\Money\Money;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CartStatsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new CartStatsWidget;
        expect($widget)->toBeInstanceOf(CartStatsWidget::class);
    });

    it('returns 4 columns', function (): void {
        $widget = new CartStatsWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getColumns');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBe(4);
    });

    it('is owner scoped when owner mode is enabled', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('filament-cart.owner.enabled', true);
        config()->set('cart.owner.include_global', false);
        config()->set('filament-cart.owner.include_global', false);
        config()->set('cart.money.default_currency', 'USD');

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-widget@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b-widget@example.com',
            'password' => 'secret',
        ]);

        $cartA = OwnerContext::withOwner($ownerA, fn () => CartSnapshot::query()->create([
            'identifier' => 'owner-a',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 2,
            'quantity' => 2,
            'subtotal' => 2500,
            'total' => 2500,
        ]));

        $cartB = OwnerContext::withOwner($ownerB, fn () => CartSnapshot::query()->create([
            'identifier' => 'owner-b',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 5,
            'quantity' => 5,
            'subtotal' => 9900,
            'total' => 9900,
        ]));

        app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

        $widget = new CartStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);

        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[0]->getValue())->toBe(1);
        expect($stats[1]->getValue())->toBe(1);
        expect($stats[2]->getValue())->toBe(2);
        expect($stats[3]->getValue())->toBe((string) Money::USD(2500));
    });

    it('includes global snapshots when include_global is enabled', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('filament-cart.owner.enabled', true);
        config()->set('cart.owner.include_global', true);
        config()->set('filament-cart.owner.include_global', true);
        config()->set('cart.money.default_currency', 'USD');

        $owner = User::query()->create([
            'name' => 'Owner Include Global',
            'email' => 'owner-include-global-widget@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner($owner, fn () => CartSnapshot::query()->create([
            'identifier' => 'owner-only',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 2000,
            'total' => 2000,
        ]));

        OwnerContext::withOwner(null, fn () => CartSnapshot::query()->create([
            'identifier' => 'global-only',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 500,
            'total' => 500,
        ]));

        app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($owner));

        $widget = new CartStatsWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);

        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[0]->getValue())->toBe(2);
        expect($stats[2]->getValue())->toBe(2);
        expect($stats[3]->getValue())->toBe((string) Money::USD(2500));
    });
});
