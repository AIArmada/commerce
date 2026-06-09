<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use Illuminate\Auth\Events\Login;

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

        $listener = new HandleUserLogin($migrationAction, $identifierResolver);

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

        $listener = new HandleUserLogin($migrationAction, $identifierResolver);

        $listener->handle(new Login('web', $user, false));

        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 1,
            'has_conflicts' => false,
            'conflicts' => collect(),
            'message' => 'Cart migrated by username!',
        ]);
    });
});
