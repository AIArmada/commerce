<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\CartIdentifierResolver;
use InvalidArgumentException;
use RuntimeException;

class CartMigrationService
{
    private array $config = [];

    private ?StorageInterface $storage = null;

    private CartIdentifierResolver $identifierResolver;

    public function __construct(
        array $config = [],
        ?StorageInterface $storage = null,
        private readonly ?MigrateGuestCartToUserAction $migrationAction = null,
        ?CartIdentifierResolver $identifierResolver = null,
    ) {
        $this->config = $config;
        $this->storage = $storage;
        $this->identifierResolver = $identifierResolver ?? app(CartIdentifierResolver::class);
    }

    /**
     * Resolve an identifier from an explicit scalar user key, an explicit guest
     * session, or the shared current-context resolution. UUID keys are preserved
     * byte-for-byte; empty values never silently fall through to another identity.
     */
    public function getIdentifier(string | int | null $userId = null, ?string $sessionId = null): string
    {
        if ($userId !== null && (string) $userId !== '') {
            return (string) $userId;
        }

        if ($sessionId !== null && $sessionId !== '') {
            return $sessionId;
        }

        return $this->identifierResolver->resolve();
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
        return $this->identifierResolver->resolve();
    }

    public function getGuestIdentifier(?string $sessionId = null): string
    {
        if ($sessionId !== null && $sessionId !== '') {
            return $sessionId;
        }

        $resolved = $this->identifierResolver->resolveSessionId();

        if ($resolved === null || $resolved === '') {
            throw new RuntimeException('Guest cart identifier cannot be determined: no session is available');
        }

        return $resolved;
    }

    public function getUserIdentifier(string | int $userId): string
    {
        $identifier = (string) $userId;

        if ($identifier === '') {
            throw new InvalidArgumentException('A user cart identifier is required: the supplied user key is empty');
        }

        return $identifier;
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        $storage = $this->storage ?: Cart::storage();

        return $storage->swapIdentifier($oldIdentifier, $newIdentifier, $instance);
    }

    public function swapGuestCartToUser(string | int $userId, string $instance = 'default', ?string $guestSessionId = null): bool
    {
        $guestIdentifier = $this->getGuestIdentifier($guestSessionId);
        $userIdentifier = $this->getUserIdentifier($userId);

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
