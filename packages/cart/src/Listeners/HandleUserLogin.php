<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use Illuminate\Auth\Events\Login;

final class HandleUserLogin
{
    public function __construct(
        private readonly MigrateCartOnLoginAction $migrationAction,
        private readonly LoginMigrationIdentifierResolver $identifierResolver,
    ) {}

    public function handle(Login $event): void
    {
        $identifiers = $this->identifierResolver->resolveFromUser($event->user);
        $sessionId = $this->identifierResolver->findCachedSessionId($identifiers);

        if ($sessionId === null) {
            return;
        }

        $result = $this->migrationAction->execute($event->user, 'default', $sessionId);

        if ($result['success'] && $result['itemsMerged'] > 0) {
            session()->flash('cart_migration', [
                'items_merged' => $result['itemsMerged'],
                'has_conflicts' => false,
                'conflicts' => collect(),
                'message' => $result['message'] ?? 'Cart migration completed',
            ]);
        }
    }
}
