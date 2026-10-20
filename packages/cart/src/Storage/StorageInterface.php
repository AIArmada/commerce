<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use AIArmada\Cart\Exceptions\CartConflictException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Core cart storage operations interface.
 *
 * This interface defines the essential storage operations required for cart functionality.
 * Implementations must provide basic CRUD operations for items, conditions, and metadata.
 */
interface StorageInterface
{
    /**
     * Set the owner for multi-tenancy scoping.
     *
     * Returns a new instance with the owner set, allowing fluent chaining.
     * When owner is set, all storage operations will be scoped to that owner.
     *
     * @param  Model|null  $owner  The owner model to scope operations to
     * @return static New instance with owner set
     */
    public function withOwner(?Model $owner): static;

    /**
     * Get the current owner type.
     *
     * @return string|null The current owner morph class or null if not set
     */
    public function getOwnerType(): ?string;

    /**
     * Get the current owner ID.
     *
     * @return string|int|null The current owner ID or null if not set
     */
    public function getOwnerId(): string | int | null;

    /**
     * Check if cart exists in storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function has(string $identifier, string $instance): bool;

    /**
     * Remove cart from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function forget(string $identifier, string $instance): void;

    /**
     * Retrieve cart items from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> Cart items array
     */
    public function getItems(string $identifier, string $instance): array;

    /**
     * Read the complete cart content together with the revision it was read at.
     *
     * This is the single coherent read boundary for read/modify/write cycles:
     * the returned revision must be passed back to the replacing write so a
     * stale replacement is rejected with {@see CartConflictException} instead
     * of silently overwriting a concurrent mutation.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function getSnapshot(string $identifier, string $instance): ?CartStorageSnapshot;

    /**
     * Retrieve cart conditions from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> Cart conditions array
     */
    public function getConditions(string $identifier, string $instance): array;

    /**
     * Atomically replace cart items, requiring the revision the replacement
     * was computed from.
     *
     * When the stored revision no longer matches the expected revision (or
     * the expected row was deleted/recreated), the write is rejected with
     * {@see CartConflictException} and no data is changed. Callers must
     * re-read via {@see self::getSnapshot()} and recompute against fresh
     * content before retrying; the stale payload must never be retried.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $items  Cart items array
     * @param  CartRevision  $expectedRevision  Revision the items were computed from; {@see CartRevision::none()} to create a new cart
     * @return CartRevision The committed revision
     *
     * @throws CartConflictException When the expected revision no longer matches
     */
    public function putItems(string $identifier, string $instance, array $items, CartRevision $expectedRevision): CartRevision;

    /**
     * Atomically replace cart conditions, requiring the revision the
     * replacement was computed from.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $conditions  Cart conditions array
     * @param  CartRevision  $expectedRevision  Revision the conditions were computed from; {@see CartRevision::none()} to create a new cart
     * @return CartRevision The committed revision
     *
     * @throws CartConflictException When the expected revision no longer matches
     */
    public function putConditions(string $identifier, string $instance, array $conditions, CartRevision $expectedRevision): CartRevision;

    /**
     * Atomically replace both items and conditions, requiring the revision
     * the replacement was computed from.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $items  Cart items array
     * @param  array<string, mixed>  $conditions  Cart conditions array
     * @param  CartRevision  $expectedRevision  Revision the payload was computed from; {@see CartRevision::none()} to create a new cart
     * @return CartRevision The committed revision
     *
     * @throws CartConflictException When the expected revision no longer matches
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions, CartRevision $expectedRevision): CartRevision;

    /**
     * Retrieve cart metadata by key.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @return mixed Metadata value or null if not found
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed;

    /**
     * Retrieve all cart metadata.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> All metadata key-value pairs
     */
    public function getAllMetadata(string $identifier, string $instance): array;

    /**
     * Store cart metadata.
     *
     * Metadata writes are atomic read/modify/write merges owned by the
     * storage: concurrent writes to other keys are preserved, and the write
     * is retried internally against fresh content when it loses a race.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @param  mixed  $value  Metadata value
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;

    /**
     * Atomically modify one metadata key from its current value.
     *
     * The compute callback receives the fresh value of the key and must
     * return the new value; returning null removes the key. The callback
     * re-runs against fresh content when the underlying row was modified
     * concurrently, so nested read/modify/write metadata updates can never
     * overwrite a concurrent change. The write is skipped when the callback
     * returns the current value unchanged.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @param  Closure(mixed):mixed  $compute  Computes the new value from the fresh current value
     * @return mixed The committed value for the key
     */
    public function mutateMetadata(string $identifier, string $instance, string $key, Closure $compute): mixed;

    /**
     * Store multiple metadata values at once.
     *
     * Metadata writes are atomic read/modify/write merges owned by the
     * storage: the batch is merged over fresh stored metadata and retried
     * internally against fresh content when it loses a race.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $metadata  Metadata key-value pairs
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void;

    /**
     * Clear all metadata for a cart.
     *
     * This is a blind write (no expected revision) keyed by the identifier:
     * it clears whatever is currently stored and bumps the cart version.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearMetadata(string $identifier, string $instance): void;

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation.
     *
     * This is a blind write (no expected revision): it resets whatever is
     * currently stored and bumps the cart version. A missing cart row is
     * inserted in its cleared state.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearAll(string $identifier, string $instance): void;

    /**
     * Get cart version for change tracking.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return int|null Version number or null if cart doesn't exist
     */
    public function getVersion(string $identifier, string $instance): ?int;

    /**
     * Get cart ID (primary key) from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null Cart UUID or null if cart doesn't exist
     */
    public function getId(string $identifier, string $instance): ?string;

    /**
     * Get cart creation timestamp.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist
     */
    public function getCreatedAt(string $identifier, string $instance): ?string;

    /**
     * Get cart last updated timestamp.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string;

    /**
     * Get cart expiration timestamp.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if no expiration
     */
    public function getExpiresAt(string $identifier, string $instance): ?string;

    /**
     * Check if a cart has expired.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function isExpired(string $identifier, string $instance): bool;

    /**
     * Swap cart identifier to transfer cart ownership.
     *
     * The swap is refused when the target identifier already holds a cart;
     * both carts are left untouched. Use the guest-to-user migration action
     * when the contents must be merged instead of transferred. A successful
     * swap bumps the cart version.
     *
     * @param  string  $oldIdentifier  The old identifier (e.g., guest session)
     * @param  string  $newIdentifier  The new identifier (e.g., user ID)
     * @param  string  $instance  Cart instance name
     * @return bool True if swap was successful
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool;

    /**
     * Backend backing key identifying the shared storage backend this instance
     * uses. Two instances can participate in one atomic transfer only when
     * their backing keys match.
     */
    public function backingKey(): string;

    /**
     * Atomically commit a guest-to-user merge transfer.
     *
     * Validates both source and target revisions and commits the target data
     * plus source consumption as one unit using this storage's backend. The
     * source storage must share the same backing store; otherwise an
     * {@see InvalidArgumentException} is thrown before any mutation.
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $metadata
     * @return CartRevision The committed source revision
     *
     * @throws CartConflictException When either revision no longer matches
     * @throws InvalidArgumentException When the backing stores are incompatible
     */
    public function commitMergeTransfer(
        StorageInterface $sourceStorage,
        string $sourceIdentifier,
        string $targetIdentifier,
        string $instance,
        array $items,
        array $conditions,
        array $metadata,
        CartRevision $sourceRevision,
        CartRevision $targetRevision,
    ): CartRevision;

    /**
     * Consume a source cart for a merge, guarded by the exact revision it was
     * read at.
     *
     * The single guarded write clears items, conditions, and metadata, records
     * the merge target for attribution, expires the row, and advances the
     * version. The write is rejected with {@see CartConflictException} when
     * the row UUID/version no longer matches the expected revision or the row
     * was already consumed, so a concurrent source write is never destroyed
     * and a source row can feed at most one committed merge.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  CartRevision  $expectedRevision  Revision the merged content was read at; must not be a creation
     * @param  string  $mergedIntoId  UUID of the cart row the content was merged into
     * @return CartRevision The committed revision
     *
     * @throws CartConflictException When the source revision no longer matches or was already consumed
     */
    public function consumeForMerge(string $identifier, string $instance, CartRevision $expectedRevision, string $mergedIntoId): CartRevision;

    /**
     * Remove a cart guarded by the exact revision the cleanup was decided at.
     *
     * Unlike {@see self::forget()}, the removal is refused when the row was
     * deleted, recreated, or modified since the expected revision, so a stale
     * cleanup can never erase a newer write. Used by automatic empty-cart
     * handling after the emptying mutation committed.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  CartRevision  $expectedRevision  Revision the cleanup was decided at; must not be a creation
     * @return bool True when the cart row was removed; false when the revision is no longer current
     *
     * @throws InvalidArgumentException When the expected revision is a creation
     */
    public function forgetIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool;

    /**
     * Clear all cart data (items, conditions, metadata) guarded by the exact
     * revision the cleanup was decided at.
     *
     * Unlike {@see self::clearAll()}, the clear is refused when the row was
     * deleted, recreated, or modified since the expected revision, so a stale
     * cleanup can never erase a newer write. A successful clear advances the
     * version. Used by automatic empty-cart handling after the emptying
     * mutation committed.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  CartRevision  $expectedRevision  Revision the cleanup was decided at; must not be a creation
     * @return bool True when the cart was cleared; false when the revision is no longer current
     *
     * @throws InvalidArgumentException When the expected revision is a creation
     */
    public function clearAllIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool;

    /**
     * Clear all carts from storage.
     */
    public function flush(): void;

    /**
     * Get all instances for a specific identifier.
     *
     * @param  string  $identifier  User/session identifier
     * @return array<string> Array of instance names
     */
    public function getInstances(string $identifier): array;

    /**
     * Remove all instances for a specific identifier.
     *
     * @param  string  $identifier  User/session identifier
     */
    public function forgetIdentifier(string $identifier): void;
}
