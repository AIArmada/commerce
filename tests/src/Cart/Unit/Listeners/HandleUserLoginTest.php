<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;

describe('HandleUserLogin', function (): void {
    beforeEach(function (): void {
        session()->forget(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY);
        session()->forget('cart_migration');
    });

    it('triggers migration action on login when a stashed session exists', function (): void {
        $user = (object) ['email' => 'test@example.com'];
        session()->put(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY, 'old-session-123');

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

        $listener = new HandleUserLogin($migrationAction, $container);

        $listener->handle(new Login('web', $user, false));

        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 2,
            'has_conflicts' => false,
            'conflicts' => collect(),
            'message' => 'Cart migrated!',
        ])->and(session(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY))->toBeNull();
    });

    it('does nothing when no session was stashed', function (): void {
        $user = (object) ['email' => 'test@example.com'];

        $migrationAction = Mockery::mock(MigrateCartOnLoginAction::class);
        $migrationAction->shouldNotReceive('execute');

        $container = Mockery::mock(Container::class);
        $container->shouldNotReceive('make');

        $listener = new HandleUserLogin($migrationAction, $container);

        $listener->handle(new Login('web', $user, false));

        expect(session('cart_migration'))->toBeNull();
    });

    it('migrates every discovered cart instance and aggregates item quantities', function (): void {
        $user = (object) ['email' => 'multi@example.com'];
        session()->put(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY, 'multi-session');

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

        $listener = new HandleUserLogin($migrationAction, $container);

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
            $container,
        );

        expect($listener)->toBeInstanceOf(HandleUserLogin::class);
    });
});
