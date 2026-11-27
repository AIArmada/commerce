<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CartInstanceManager', function (): void {
    it('resolves cart instance with rules factory', function (): void {
        $rulesFactory = Mockery::mock(RulesFactoryInterface::class);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getMetadata')
            ->with('session-123', 'default', 'dynamic_conditions')
            ->andReturn([]);

        $cart = new Cart($storage, 'session-123');

        // Mock the facade root manually to avoid Final class issues
        // We create a mock that behaves like the underlying service but is not final
        $facadeMock = Mockery::mock();
        $facadeMock->shouldReceive('getCartInstance')
            ->with('default', 'session-123')
            ->andReturn($cart);

        CartFacade::swap($facadeMock);

        $manager = new CartInstanceManager($rulesFactory);
        $result = $manager->resolve('default', 'session-123');

        expect($result)->toBe($cart);
        // We can check if rules factory was set by calling getRulesFactory if available or check behavior
        // Cart class doesn't seem to expose getRulesFactory publicly in interface?
        // But withRulesFactory returns the cart instance.
    });

    it('prepares cart with rules factory', function (): void {
        $rulesFactory = Mockery::mock(RulesFactoryInterface::class);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getMetadata')
            ->with('session-123', 'default', 'dynamic_conditions')
            ->andReturn([]);

        $cart = new Cart($storage, 'session-123');

        $manager = new CartInstanceManager($rulesFactory);
        $result = $manager->prepare($cart);

        expect($result)->toBe($cart);
    });

    it('resolves a snapshot only inside the matching ambient owner context', function (): void {
        config()->set('cart.owner.enabled', true);

        $rulesFactory = Mockery::mock(RulesFactoryInterface::class);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getMetadata')
            ->with('session-123', 'default', 'dynamic_conditions')
            ->andReturn([]);

        $cart = new Cart($storage, 'session-123');

        $owner = User::query()->create([
            'name' => 'Snapshot Owner',
            'email' => 'snapshot-instance-owner@example.com',
            'password' => 'secret',
        ]);

        $snapshot = new CartSnapshot;
        $snapshot->instance = 'default';
        $snapshot->identifier = 'session-123';
        $snapshot->owner_type = $owner->getMorphClass();
        $snapshot->owner_id = (string) $owner->getKey();

        $facadeMock = Mockery::mock();
        $facadeMock->shouldReceive('getCartInstance')
            ->with('default', 'session-123')
            ->andReturn($cart);

        CartFacade::swap($facadeMock);

        $manager = new CartInstanceManager($rulesFactory);

        expect(fn () => $manager->resolveForSnapshot($snapshot->toSnapshotData()))
            ->toThrow(AuthorizationException::class);

        $result = OwnerContext::withOwner($owner, fn () => $manager->resolveForSnapshot($snapshot->toSnapshotData()));

        expect($result)->toBe($cart);
    });

    it('rejects writable snapshot resolution for global snapshots in tenant context', function (): void {
        config()->set('cart.owner.enabled', true);

        $owner = User::query()->create([
            'name' => 'Tenant Owner',
            'email' => 'tenant-instance-owner@example.com',
            'password' => 'secret',
        ]);

        $snapshot = new CartSnapshot;
        $snapshot->instance = 'default';
        $snapshot->identifier = 'global-session';
        $snapshot->owner_type = null;
        $snapshot->owner_id = null;

        $manager = new CartInstanceManager(Mockery::mock(RulesFactoryInterface::class));

        expect(fn () => OwnerContext::withOwner($owner, fn () => $manager->resolveForSnapshot($snapshot->toSnapshotData())))
            ->toThrow(AuthorizationException::class);
    });
});
