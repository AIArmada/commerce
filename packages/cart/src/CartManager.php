<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Services\CartFactory;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Support\CartIdentifierResolver;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Cart Manager handles global instance switching for the Cart facade.
 *
 * The ambient manager re-resolves storage at every public entry point and keeps
 * all mutable state partitioned by owner identity, so an A -> B -> A context
 * switch inside one lifecycle restores A's own state without exposing it to B.
 *
 * Managers created through forOwner() are deliberately pinned to a single
 * owner and never re-resolve ambient storage.
 */
class CartManager implements CartManagerInterface
{
    /** @var array<string, StorageInterface> */
    private array $storages = [];

    /** @var array<string, Cart> */
    private array $currentCarts = [];

    /** @var array<string, string> */
    private array $currentInstances = [];

    /** @var array<string, array<string, Cart>> */
    private array $cartCaches = [];

    private CartIdentifierResolver $identifierResolver;

    /**
     * @param  (Closure(): StorageInterface)|null  $storageResolver  Re-resolves the ambient
     *                                                               storage for the current owner context on every
     *                                                               entry point. Null pins this manager to a single
     *                                                               storage (direct construction, forOwner()).
     */
    public function __construct(
        private StorageInterface $storage,
        private ?Dispatcher $events = null,
        private bool $eventsEnabled = true,
        private ?CartConditionResolver $conditionResolver = null,
        private ?CartFactory $cartFactory = null,
        private ?Closure $storageResolver = null,
        ?CartIdentifierResolver $identifierResolver = null,
    ) {
        $this->identifierResolver = $identifierResolver ?? app(CartIdentifierResolver::class);
        $this->conditionResolver ??= app(CartConditionResolver::class);
        $this->cartFactory ??= new CartFactory(
            storage: $this->storage,
            conditionResolver: $this->conditionResolver,
            events: $this->events,
            eventsEnabled: $this->eventsEnabled,
        );

        // Eagerly initialize the active partition so identifier resolution
        // failures surface at resolution time, as they historically did.
        $this->getCurrentCart();
    }

    /**
     * Proxy all other method calls to the current cart instance
     *
     * @param  array<string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->getCurrentCart()->{$method}(...$arguments);
    }

    /**
     * Get the current cart instance for the active owner partition
     */
    public function getCurrentCart(): Cart
    {
        $storage = $this->resolveStorage();
        $key = $this->partitionKey($storage);

        return $this->currentCarts[$key] ??= $this->cartFactoryFor($storage)->make(
            identifier: $this->resolveIdentifier(),
            instanceName: $this->currentInstances[$key] ?? 'default',
            events: $this->events,
        );
    }

    /**
     * Get a cart instance without changing the global state
     */
    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->cartFactoryFor($this->resolveStorage())->make(
            identifier: $this->resolveIdentifier($identifier),
            instanceName: $name,
            events: $this->events,
        );
    }

    /**
     * Get the current instance name for the active owner partition
     */
    public function instance(): string
    {
        $key = $this->partitionKey($this->resolveStorage());

        return $this->currentInstances[$key] ?? 'default';
    }

    /**
     * Set the current cart instance globally
     */
    public function setInstance(string $name): static
    {
        $storage = $this->resolveStorage();
        $key = $this->partitionKey($storage);

        if (($this->currentInstances[$key] ?? 'default') === $name) {
            return $this;
        }

        $identifier = $this->getCurrentCart()->getIdentifier();

        $this->currentInstances[$key] = $name;
        $this->currentCarts[$key] = $this->cartFactoryFor($storage)->make(
            identifier: $identifier,
            instanceName: $name,
            events: $this->events,
        );

        return $this;
    }

    /**
     * Set the current cart identifier globally
     */
    public function setIdentifier(string $identifier): static
    {
        $storage = $this->resolveStorage();
        $key = $this->partitionKey($storage);

        if ($this->getCurrentCart()->getIdentifier() === $identifier) {
            return $this;
        }

        $this->currentCarts[$key] = $this->cartFactoryFor($storage)->make(
            identifier: $identifier,
            instanceName: $this->currentInstances[$key] ?? 'default',
            events: $this->events,
        );

        return $this;
    }

    /**
     * Reset cart identifier to default (session/user ID)
     *
     * Useful for reverting to automatic identifier resolution after
     * setting a custom identifier during runtime.
     */
    public function forgetIdentifier(): static
    {
        $storage = $this->resolveStorage();
        $key = $this->partitionKey($storage);
        $defaultIdentifier = $this->resolveIdentifier();

        if ($this->getCurrentCart()->getIdentifier() !== $defaultIdentifier) {
            $this->currentCarts[$key] = $this->cartFactoryFor($storage)->make(
                identifier: $defaultIdentifier,
                instanceName: $this->currentInstances[$key] ?? 'default',
                events: $this->events,
            );
        }

        return $this;
    }

    /**
     * Create a new cart manager instance scoped to a specific owner
     *
     * Use this for admin operations that need to operate on a specific owner's carts.
     * The returned manager is deliberately pinned to the given owner: it has
     * isolated storage that only accesses the specified owner's data and never
     * re-resolves ambient owner context.
     *
     * @param  Model  $owner  The owner model to scope operations to
     */
    public function forOwner(Model $owner): static
    {
        $scopedStorage = $this->storage->withOwner($owner);

        // @phpstan-ignore-next-line late static binding constructor
        return new static(
            storage: $scopedStorage,
            events: $this->events,
            eventsEnabled: $this->eventsEnabled,
            conditionResolver: $this->conditionResolver,
            cartFactory: $this->cartFactoryFor($scopedStorage),
        );
    }

    /**
     * Get the current owner type if operating in owner-scoped mode
     */
    public function getOwnerType(): ?string
    {
        return $this->resolveStorage()->getOwnerType();
    }

    /**
     * Get the current owner ID if operating in owner-scoped mode
     */
    public function getOwnerId(): string | int | null
    {
        return $this->resolveStorage()->getOwnerId();
    }

    /**
     * Get a cart instance by its UUID
     *
     * This loads a cart object using its database primary key (UUID).
     * Useful for retrieving carts from payment systems, orders, or webhooks.
     *
     * @param  string  $uuid  The cart UUID (primary key from carts table)
     * @return Cart|null Cart instance or null if not found
     */
    public function getById(string $uuid): ?Cart
    {
        $storage = $this->resolveStorage();
        $key = $this->partitionKey($storage);

        if (isset($this->cartCaches[$key][$uuid])) {
            return $this->cartCaches[$key][$uuid];
        }

        if (! $storage instanceof DatabaseStorage) {
            return null;
        }

        $snapshot = $storage->findSnapshotById($uuid);

        if (! $snapshot) {
            return null;
        }

        return $this->cartCaches[$key][$uuid] = $this->getCartInstance($snapshot->instance, $snapshot->identifier);
    }

    /**
     * Swap cart ownership by transferring cart from old identifier to new identifier.
     *
     * This ensures the new identifier has an active cart by transferring
     * the cart from the old identifier. This prevents cart abandonment by
     * ensuring continued cart activity under the new identifier.
     *
     * @param  string  $oldIdentifier  The old identifier (e.g., guest session)
     * @param  string  $newIdentifier  The new identifier (e.g., user ID)
     * @param  string  $instance  The cart instance name (e.g., 'default', 'wishlist')
     * @return bool True if swap was successful (new identifier now has the cart)
     */
    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        $storage = $this->resolveStorage();
        $key = $this->partitionKey($storage);

        $swapped = $storage->swapIdentifier($oldIdentifier, $newIdentifier, $instance);

        if ($swapped) {
            unset($this->cartCaches[$key]);
        }

        return $swapped;
    }

    /**
     * Resolve the storage for the currently active owner context.
     *
     * Ambient managers re-resolve through the storage resolver on every call;
     * pinned managers always return their single storage instance.
     */
    private function resolveStorage(): StorageInterface
    {
        if ($this->storageResolver === null) {
            return $this->storage;
        }

        /** @var StorageInterface $storage */
        $storage = ($this->storageResolver)();

        return $this->storages[$this->partitionKey($storage)] ??= $storage;
    }

    /**
     * Partition key for the owner tuple currently bound to the given storage.
     */
    private function partitionKey(StorageInterface $storage): string
    {
        return OwnerScopeKey::forTypeAndId($storage->getOwnerType(), $storage->getOwnerId());
    }

    /**
     * Build a cart factory bound to the given storage while keeping every
     * registered condition provider, handler, and event setting.
     */
    private function cartFactoryFor(StorageInterface $storage): CartFactory
    {
        return $this->cartFactory->withStorage($storage);
    }

    /**
     * Resolve the storage identifier through the shared resolver: authenticated
     * user ID first, then session ID for guests. UUID keys are preserved
     * byte-for-byte and a missing identifier fails instead of degrading.
     */
    private function resolveIdentifier(?string $customIdentifier = null): string
    {
        return $this->identifierResolver->resolve($customIdentifier);
    }
}
