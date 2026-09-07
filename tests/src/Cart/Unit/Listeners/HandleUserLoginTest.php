<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;

describe('HandleUserLogin', function (): void {
    it('triggers migration action on login when cached session exists', function (): void {
        $user = (object) ['email' => 'test@example.com'];

        $identifierResolver = Mockery::mock(new LoginMigrationIdentifierResolver);
        $identifierResolver->shouldReceive('resolveFromUser')
            ->with($user)
            ->andReturn(['test@example.com'])
            ->once();
        $identifierResolver->shouldReceive('findCachedSessionId')
            ->with(['test@example.com'])
            ->andReturn('old-session-123')
            ->once();

        $migrationAction = Mockery::mock(MigrateCartOnLoginAction::class);
        $migrationAction->shouldReceive('execute')
            ->with($user, 'default', 'old-session-123')
            ->andReturn([
                'success' => true,
                'itemsMerged' => 2,
                'message' => 'Cart migrated!',
            ])
            ->once();

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null)->once();
        $storage->shouldReceive('getInstances')->with('old-session-123')->andReturn([])->once();

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->with(StorageInterface::class)->andReturn($storage)->once();

        $listener = new HandleUserLogin($migrationAction, $identifierResolver, $container);

        $listener->handle(new Login('web', $user, false));

        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 2,
            'has_conflicts' => false,
            'conflicts' => collect(),
            'message' => 'Cart migrated!',
        ]);
    });

    it('falls back to cached username when the user model also has an email', function (): void {
        $user = (object) [
            'email' => 'test@example.com',
            'username' => 'testuser',
        ];

        $identifierResolver = Mockery::mock(new LoginMigrationIdentifierResolver);
        $identifierResolver->shouldReceive('resolveFromUser')
            ->with($user)
            ->andReturn(['test@example.com', 'testuser'])
            ->once();
        $identifierResolver->shouldReceive('findCachedSessionId')
            ->with(['test@example.com', 'testuser'])
            ->andReturn('old-session-username')
            ->once();

        $migrationAction = Mockery::mock(MigrateCartOnLoginAction::class);
        $migrationAction->shouldReceive('execute')
            ->with($user, 'default', 'old-session-username')
            ->andReturn([
                'success' => true,
                'itemsMerged' => 1,
                'message' => 'Cart migrated by username!',
            ])
            ->once();

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null)->once();
        $storage->shouldReceive('getInstances')->with('old-session-username')->andReturn([])->once();

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->with(StorageInterface::class)->andReturn($storage)->once();

        $listener = new HandleUserLogin($migrationAction, $identifierResolver, $container);

        $listener->handle(new Login('web', $user, false));

        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 1,
            'has_conflicts' => false,
            'conflicts' => collect(),
            'message' => 'Cart migrated by username!',
        ]);
    });

    it('migrates every discovered cart instance and aggregates item quantities', function (): void {
        $user = (object) ['email' => 'multi@example.com'];

        $identifierResolver = Mockery::mock(new LoginMigrationIdentifierResolver);
        $identifierResolver->shouldReceive('resolveFromUser')->with($user)->andReturn(['multi@example.com']);
        $identifierResolver->shouldReceive('findCachedSessionId')->with(['multi@example.com'])->andReturn('multi-session');

        $migrationAction = Mockery::mock(MigrateCartOnLoginAction::class);
        $migrationAction->shouldReceive('execute')
            ->with($user, 'default', 'multi-session')
            ->andReturn([
                'success' => true,
                'itemsMerged' => 2,
                'message' => 'Default cart migrated',
            ])
            ->once();
        $migrationAction->shouldReceive('execute')
            ->with($user, 'wishlist', 'multi-session')
            ->andReturn([
                'success' => true,
                'itemsMerged' => 3,
                'message' => 'Wishlist migrated',
            ])
            ->once();

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null)->once();
        $storage->shouldReceive('getInstances')->with('multi-session')->andReturn(['default', 'wishlist'])->once();

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->with(StorageInterface::class)->andReturn($storage)->once();

        $listener = new HandleUserLogin($migrationAction, $identifierResolver, $container);

        $listener->handle(new Login('web', $user, false));

        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 5,
            'has_conflicts' => false,
            'conflicts' => collect(),
            'message' => 'Wishlist migrated',
        ]);
    });

    it('does not resolve storage while the listener is being constructed', function (): void {
        config()->set('cart.owner.enabled', true);

        $container = Mockery::mock(Container::class);
        $container->shouldNotReceive('make');

        $listener = new HandleUserLogin(
            Mockery::mock(MigrateCartOnLoginAction::class),
            Mockery::mock(LoginMigrationIdentifierResolver::class),
            $container,
        );

        expect($listener)->toBeInstanceOf(HandleUserLogin::class);
    });
});
