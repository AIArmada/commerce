<?php

declare(strict_types=1);

namespace AIArmada\Cart\Listeners;

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;

final class HandleUserLogin
{
    /**
     * Maximum guest-cart instances migrated on a single login.
     */
    private const MAX_LOGIN_INSTANCES = 25;

    public function __construct(
        private readonly MigrateCartOnLoginAction $migrationAction,
        private readonly Container $container,
    ) {}

    public function handle(Login $event): void
    {
        if (! app()->bound('session')) {
            return;
        }

        $sessionId = session()->pull(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY);

        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $storage = $this->container->make(StorageInterface::class);
        $guestStorage = $storage->getOwnerType() !== null
            ? $storage->withOwner(null)
            : $storage;
        $instances = array_slice(
            array_values(array_unique($guestStorage->getInstances($sessionId))),
            0,
            self::MAX_LOGIN_INSTANCES
        );

        if ($instances === []) {
            $instances = ['default'];
        }

        $itemsMerged = 0;
        $message = 'Cart migration completed';

        foreach ($instances as $instance) {
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
