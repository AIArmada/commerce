<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Support\Facades\Auth;

class CartMigrationService
{
    private array $config = [];

    private ?StorageInterface $storage = null;

    public function __construct(
        array $config = [],
        ?StorageInterface $storage = null,
        private readonly ?MigrateGuestCartToUserAction $migrationAction = null,
    ) {
        $this->config = $config;
        $this->storage = $storage;
    }

    public function getIdentifier(?int $userId = null, ?string $sessionId = null): string
    {
        if ($userId) {
            return (string) $userId;
        }

        if ($sessionId) {
            return $sessionId;
        }

        return session()->getId();
    }

    public function migrateGuestCartToUser(string | int $userId, string $instance, string $sessionId): bool
    {
        $strategyName = $this->config['merge_strategy'] ?? config('cart.migration.merge_strategy', 'add_quantities');

        return $this->resolveMigrationAction()->execute($userId, $instance, $sessionId, null, $strategyName);
    }

    public function migrateGuestCartForUser(mixed $user, string $instance, ?string $sessionId): object
    {
        return $this->resolveMigrationAction()->executeForUser($user, $instance, $sessionId);
    }

    public function getCurrentIdentifier(): string
    {
        if (Auth::check()) {
            return $this->getIdentifier((int) Auth::id());
        }

        return $this->getIdentifier(null, session()->getId());
    }

    public function getGuestIdentifier(?string $sessionId = null): string
    {
        return $this->getIdentifier(null, $sessionId ?? session()->getId());
    }

    public function getUserIdentifier(int $userId): string
    {
        return $this->getIdentifier($userId);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        $storage = $this->storage ?: Cart::storage();

        return $storage->swapIdentifier($oldIdentifier, $newIdentifier, $instance);
    }

    public function swapGuestCartToUser(int $userId, string $instance = 'default', ?string $guestSessionId = null): bool
    {
        $guestIdentifier = $this->getIdentifier(null, $guestSessionId ?? session()->getId());
        $userIdentifier = $this->getIdentifier($userId);

        return $this->swap($guestIdentifier, $userIdentifier, $instance);
    }

    private function resolveMigrationAction(): MigrateGuestCartToUserAction
    {
        if ($this->migrationAction !== null) {
            return $this->migrationAction;
        }

        return new MigrateGuestCartToUserAction(
            app(CartMergeStrategyRegistry::class),
            $this->storage,
        );
    }
}
