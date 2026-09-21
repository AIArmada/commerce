<?php

declare(strict_types=1);

use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('cart.migration.auto_migrate_on_login', true);
});

it('integration: fails fast when cart storage is resolved without an owner in owner mode', function (): void {
    config()->set('cart.owner.enabled', true);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
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
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $provider = new CartServiceProvider(app());
    $provider->register();

    $storage = OwnerContext::withOwner(null, fn () => app()->make('cart.storage'));

    expect($storage)->toBeInstanceOf(DatabaseStorage::class);
});
