<?php

declare(strict_types=1);

use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Features\LiveCart\Persistence\DatabaseStorage;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('cart.migration.auto_migrate_on_login', true);
});

it('integration: fails fast when cart storage is resolved without an owner in owner mode', function (): void {
    config()->set('cart.owner.enabled', true);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    $provider = new CartServiceProvider(app());
    $provider->register();

    expect(fn () => app()->make('cart.storage'))
        ->toThrow(RuntimeException::class, 'Cart owner is enabled but no owner was resolved while resolving cart storage.');
});

it('integration: allows explicit global cart storage resolution in owner mode', function (): void {
    config()->set('cart.owner.enabled', true);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    $provider = new CartServiceProvider(app());
    $provider->register();

    $storage = OwnerContext::withOwner(null, fn () => app()->make('cart.storage'));

    expect($storage)->toBeInstanceOf(DatabaseStorage::class);
});

it('registers the canonical cart runtime manager interface', function (): void {
    $provider = new CartServiceProvider(app());
    $provider->register();

    expect(app()->getAlias(CartManagerInterface::class))->toBe('cart')
        ->and(app()->bound(CartManagerInterface::class))->toBeTrue();
});

it('keeps decorators and interface resolution in the same cart lifecycle', function (): void {
    config(['cart.owner.enabled' => false]);

    $provider = new CartServiceProvider(app());
    $provider->register();

    $resolutions = 0;
    app()->extend('cart', function (CartManagerInterface $manager) use (&$resolutions): CartManagerInterface {
        $resolutions++;

        return $manager;
    });

    $first = app(CartManagerInterface::class);

    expect(app('cart'))->toBe($first);

    app()->forgetInstance('cart');

    $second = app(CartManagerInterface::class);

    expect($second)->not->toBe($first)
        ->and(app('cart'))->toBe($second)
        ->and($resolutions)->toBe(2);
});
