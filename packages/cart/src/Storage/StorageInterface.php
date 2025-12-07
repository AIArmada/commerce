<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use Illuminate\Database\Eloquent\Model;

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
    public function getOwnerId(): string|int|null;

    /**
     * Check if cart exists in storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function has(string $identifier, string $instance): bool;

    /**
     * Remove cart from storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function forget(string $identifier, string $instance): void;

    /**
     * Clear all carts from storage
     */
    public function flush(): void;

    /**
     * Get all instances for a specific identifier
     *
     * @param  string  $identifier  User/session identifier
     * @return array<string> Array of instance names
     */
    public function getInstances(string $identifier): array;

    /**
     * Remove all instances for a specific identifier
     *
     * @param  string  $identifier  User/session identifier
     */
    public function forgetIdentifier(string $identifier): void;

    /**
     * Retrieve cart items from storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> Cart items array
     */
    public function getItems(string $identifier, string $instance): array;

    /**
     * Retrieve cart conditions from storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> Cart conditions array
     */
    public function getConditions(string $identifier, string $instance): array;

    /**
     * Store cart items in storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $items  Cart items array
     */
    public function putItems(string $identifier, string $instance, array $items): void;

    /**
     * Store cart conditions in storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $conditions  Cart conditions array
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void;

    /**
     * Store both items and conditions in storage
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $items  Cart items array
     * @param  array<string, mixed>  $conditions  Cart conditions array
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void;

    /**
     * Store cart metadata
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @param  mixed  $value  Metadata value
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;

    /**
     * Store multiple metadata values at once
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $metadata  Metadata key-value pairs
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void;

    /**
     * Retrieve cart metadata
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @return mixed Metadata value or null if not found
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed;

    /**
     * Retrieve all cart metadata
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> All metadata key-value pairs
     */
    public function getAllMetadata(string $identifier, string $instance): array;

    /**
     * Clear all metadata for a cart
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearMetadata(string $identifier, string $instance): void;

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation
     *
     * This is more efficient than calling putItems, putConditions, and clearMetadata separately.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearAll(string $identifier, string $instance): void;

    /**
     * Get cart version for change tracking
     * Returns the version number used for optimistic locking and change detection
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return int|null Version number or null if cart doesn't exist
     */
    public function getVersion(string $identifier, string $instance): ?int;

    /**
     * Get cart ID (primary key) from storage
     * Useful for linking carts to external systems (payment gateways, orders, etc.)
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null Cart UUID or null if cart doesn't exist
     */
    public function getId(string $identifier, string $instance): ?string;

    /**
     * Swap cart identifier to transfer cart ownership.
     * This changes cart ownership from old identifier to new identifier by updating the identifier column.
     * The objective is to ensure the new identifier has an active cart, preventing cart abandonment.
     *
     * @param  string  $oldIdentifier  The old identifier (e.g., guest session)
     * @param  string  $newIdentifier  The new identifier (e.g., user ID)
     * @param  string  $instance  Cart instance name
     * @return bool True if swap was successful (new identifier now has the cart)
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool;

    /**
     * Get cart creation timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist
     */
    public function getCreatedAt(string $identifier, string $instance): ?string;

    /**
     * Get cart last updated timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string;

    /**
     * Get cart expiration timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist or no expiration
     */
    public function getExpiresAt(string $identifier, string $instance): ?string;

    /**
     * Check if a cart has expired
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return bool True if cart has expired
     */
    public function isExpired(string $identifier, string $instance): bool;

    // =========================================================================
    // AI & Analytics Methods (Phase 0.2)
    // =========================================================================

    /**
     * Get last activity timestamp for engagement tracking
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if not tracked
     */
    public function getLastActivityAt(string $identifier, string $instance): ?string;

    /**
     * Update last activity timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function touchLastActivity(string $identifier, string $instance): void;

    /**
     * Get checkout started timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if checkout not started
     */
    public function getCheckoutStartedAt(string $identifier, string $instance): ?string;

    /**
     * Mark checkout as started for conversion funnel tracking
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function markCheckoutStarted(string $identifier, string $instance): void;

    /**
     * Get checkout abandoned timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if not abandoned
     */
    public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string;

    /**
     * Mark checkout as abandoned for recovery tracking
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function markCheckoutAbandoned(string $identifier, string $instance): void;

    /**
     * Get number of recovery attempts made
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return int Number of recovery attempts (0 if none)
     */
    public function getRecoveryAttempts(string $identifier, string $instance): int;

    /**
     * Increment recovery attempts counter
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function incrementRecoveryAttempts(string $identifier, string $instance): void;

    /**
     * Get recovered at timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if not recovered
     */
    public function getRecoveredAt(string $identifier, string $instance): ?string;

    /**
     * Mark cart as recovered (user returned after abandonment)
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function markRecovered(string $identifier, string $instance): void;

    /**
     * Clear all abandonment tracking data (checkout started, abandoned, recovery)
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearAbandonmentTracking(string $identifier, string $instance): void;

    // =========================================================================
    // Event Sourcing Methods (Phase 0.3)
    // =========================================================================

    /**
     * Get current event stream position for replay
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return int Event stream position (0 if not set)
     */
    public function getEventStreamPosition(string $identifier, string $instance): int;

    /**
     * Update event stream position after recording events
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  int  $position  New stream position
     */
    public function setEventStreamPosition(string $identifier, string $instance, int $position): void;

    /**
     * Get aggregate schema version for migrations
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string Version string (e.g., "1.0")
     */
    public function getAggregateVersion(string $identifier, string $instance): string;

    /**
     * Update aggregate schema version
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $version  New version string
     */
    public function setAggregateVersion(string $identifier, string $instance, string $version): void;

    /**
     * Get last snapshot timestamp
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if no snapshot
     */
    public function getSnapshotAt(string $identifier, string $instance): ?string;

    /**
     * Update snapshot timestamp after taking a snapshot
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function markSnapshotTaken(string $identifier, string $instance): void;
}
