<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\LoginMigrationIdentifierResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;

final class HandleUserLogin
{
    public function __construct(
        private readonly MigrateCartOnLoginAction $migrationAction,
        private readonly LoginMigrationIdentifierResolver $identifierResolver,
        private readonly Container $container,
    ) {}

    public function handle(Login $event): void
    {
        $identifiers = $this->identifierResolver->resolveFromUser($event->user);
        $sessionId = $this->identifierResolver->findCachedSessionId($identifiers);

        if ($sessionId === null) {
            return;
        }

        $storage = $this->container->make(StorageInterface::class);
        $guestStorage = $storage->getOwnerType() !== null
            ? $storage->withOwner(null)
            : $storage;
        $instances = $guestStorage->getInstances($sessionId);

        if ($instances === []) {
            $instances = ['default'];
        }

        $itemsMerged = 0;
        $message = 'Cart migration completed';

        foreach (array_values(array_unique($instances)) as $instance) {
            $result = $this->migrationAction->execute($event->user, $instance, $sessionId);

            if (! $result['success']) {
                continue;
            }

            $itemsMerged += $result['itemsMerged'];
            $message = $result['message'] ?? $message;
        }

        if ($itemsMerged > 0) {
            session()->flash('cart_migration', [
                'items_merged' => $itemsMerged,
                'has_conflicts' => false,
                'conflicts' => collect(),
                'message' => $message,
            ]);
        }
    }
}
