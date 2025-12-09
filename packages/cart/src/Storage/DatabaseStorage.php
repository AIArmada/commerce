<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use AIArmada\Cart\Exceptions\CartConflictException;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface as Database;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

final readonly class DatabaseStorage implements StorageInterface
{
    /**
     * @param  int|null  $ttl  Time-to-live in seconds (null = no expiration)
     * @param  string|null  $ownerType  Owner morph class for multi-tenancy scoping
     * @param  string|int|null  $ownerId  Owner ID for multi-tenancy scoping
     */
    public function __construct(
        private Database $database,
        private string $table = 'carts',
        private ?int $ttl = null,
        private ?string $ownerType = null,
        private string | int | null $ownerId = null,
    ) {}

    /**
     * Create a new instance with the specified owner.
     */
    public function withOwner(?Model $owner): static
    {
        return new self(
            database: $this->database,
            table: $this->table,
            ttl: $this->ttl,
            ownerType: $owner?->getMorphClass(),
            ownerId: $owner?->getKey(),
        );
    }

    /**
     * Get the current owner type.
     */
    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    /**
     * Get the current owner ID.
     */
    public function getOwnerId(): string | int | null
    {
        return $this->ownerId;
    }

    /**
     * Retrieve cart items from storage
     *
     * @return array<string, mixed>
     */
    public function getItems(string $identifier, string $instance): array
    {
        return $this->getJsonColumn($identifier, $instance, 'items');
    }

    /**
     * Retrieve cart conditions from storage
     *
     * @return array<string, mixed>
     */
    public function getConditions(string $identifier, string $instance): array
    {
        return $this->getJsonColumn($identifier, $instance, 'conditions');
    }

    /**
     * Store cart items in storage
     *
     * @param  array<string, mixed>  $items
     */
    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->updateJsonColumn($identifier, $instance, 'items', $items, 'items update');
    }

    /**
     * Store cart conditions in storage
     *
     * @param  array<string, mixed>  $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->updateJsonColumn($identifier, $instance, 'conditions', $conditions, 'conditions update');
    }

    /**
     * Store both items and conditions in storage
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->validateDataSize($items, 'items');
        $this->validateDataSize($conditions, 'conditions');

        // Items should always have data when using putBoth, but conditions might be empty
        $itemsJson = $this->encodeData($items, 'items');
        $conditionsJson = empty($conditions) ? null : $this->encodeData($conditions, 'conditions');

        $this->performCasUpdate($identifier, $instance, [
            'items' => $itemsJson,
            'conditions' => $conditionsJson,
        ], 'both items and conditions update');
    }

    /**
     * Check if cart exists in storage
     */
    public function has(string $identifier, string $instance): bool
    {
        return $this->baseQuery($identifier, $instance)->exists();
    }

    /**
     * Remove cart from storage
     */
    public function forget(string $identifier, string $instance): void
    {
        $this->baseQuery($identifier, $instance)->delete();
    }

    /**
     * Clear all carts from storage
     * WARNING: This is a dangerous operation that should be used with extreme caution
     */
    public function flush(): void
    {
        // Only allow flush in testing environments to prevent accidental data loss
        if (app()->environment(['testing', 'local'])) {
            $query = $this->database->table($this->table);

            // If owner scoped, only flush owner's carts
            if ($this->ownerType !== null && $this->ownerId !== null) {
                $query->where('owner_type', $this->ownerType)
                    ->where('owner_id', $this->ownerId);
                $query->delete();
            } else {
                $query->truncate();
            }
        } else {
            throw new RuntimeException('Flush operation is only allowed in testing and local environments');
        }
    }

    /**
     * Get all instances for a specific identifier
     *
     * @return array<string>
     */
    public function getInstances(string $identifier): array
    {
        $query = $this->database->table($this->table)
            ->where('identifier', $identifier);

        if ($this->ownerType !== null && $this->ownerId !== null) {
            $query->where('owner_type', $this->ownerType)
                ->where('owner_id', $this->ownerId);
        }

        return $query->pluck('instance')->toArray();
    }

    /**
     * Remove all instances for a specific identifier
     */
    public function forgetIdentifier(string $identifier): void
    {
        $query = $this->database->table($this->table)
            ->where('identifier', $identifier);

        if ($this->ownerType !== null && $this->ownerId !== null) {
            $query->where('owner_type', $this->ownerType)
                ->where('owner_id', $this->ownerId);
        }

        $query->delete();
    }

    /**
     * Store cart metadata
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->database->transaction(function () use ($identifier, $instance, $key, $value): void {
            // Get existing metadata
            $existing = $this->baseQuery($identifier, $instance)->value('metadata');

            $metadata = $this->decodeData($existing, 'metadata', []);
            $metadata[$key] = $value;
            $this->validateDataSize($metadata, 'metadata');

            // Filter out null values and convert empty metadata to null
            $metadata = array_filter($metadata, fn ($value) => $value !== null);
            $metadataJson = empty($metadata) ? null : $this->encodeData($metadata, 'metadata');

            $this->performCasUpdate($identifier, $instance, [
                'metadata' => $metadataJson,
            ], 'metadata update');
        });
    }

    /**
     * Store multiple metadata values at once
     *
     * @param  array<string, mixed>  $metadata
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        $this->database->transaction(function () use ($identifier, $instance, $metadata): void {
            // Get existing metadata
            $existing = $this->baseQuery($identifier, $instance)->value('metadata');

            $existingMetadata = $this->decodeData($existing, 'metadata', []);

            // Merge new metadata with existing
            $mergedMetadata = array_merge($existingMetadata, $metadata);
            $this->validateDataSize($mergedMetadata, 'metadata');

            // Filter out null values and convert empty metadata to null
            $mergedMetadata = array_filter($mergedMetadata, fn ($value) => $value !== null);
            $metadataJson = empty($mergedMetadata) ? null : $this->encodeData($mergedMetadata, 'metadata');

            $this->performCasUpdate($identifier, $instance, [
                'metadata' => $metadataJson,
            ], 'metadata batch update');
        });
    }

    /**
     * Retrieve cart metadata
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $result = $this->baseQuery($identifier, $instance)->value('metadata');

        if (! $result) {
            return null;
        }

        $metadata = $this->decodeData($result, 'metadata', []);

        return $metadata[$key] ?? null;
    }

    /**
     * Retrieve all cart metadata
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $result = $this->baseQuery($identifier, $instance)->value('metadata');

        if (! $result) {
            return [];
        }

        return $this->decodeData($result, 'metadata', []);
    }

    /**
     * Clear all metadata for a cart
     */
    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->database->transaction(function () use ($identifier, $instance): void {
            $this->performCasUpdate($identifier, $instance, [
                'metadata' => null,
            ], 'metadata clear');
        });
    }

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation
     */
    public function clearAll(string $identifier, string $instance): void
    {
        $this->database->transaction(function () use ($identifier, $instance): void {
            $this->performCasUpdate($identifier, $instance, [
                'items' => json_encode([]),
                'conditions' => null,
                'metadata' => null,
            ], 'clear all');
        });
    }

    /**
     * Get cart version for change tracking
     */
    public function getVersion(string $identifier, string $instance): ?int
    {
        $version = $this->baseQuery($identifier, $instance)->value('version');

        return $version !== null ? (int) $version : null;
    }

    /**
     * Get cart ID (primary key) from storage
     */
    public function getId(string $identifier, string $instance): ?string
    {
        return $this->baseQuery($identifier, $instance)->value('id');
    }

    /**
     * Swap cart identifier by directly updating the identifier column.
     * This transfers cart ownership from old identifier to new identifier.
     * The objective is to change ownership to ensure target has an active cart.
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        // Check if source cart exists
        if (! $this->has($oldIdentifier, $instance)) {
            return false;
        }

        // Use transaction to handle the swap safely
        return $this->database->transaction(function () use ($oldIdentifier, $newIdentifier, $instance) {
            // First, delete any existing cart with the target identifier
            // This ensures the swap always succeeds by removing conflicts
            $this->baseQuery($newIdentifier, $instance)->delete();

            // Now update the source cart to use the new identifier
            $updated = $this->baseQuery($oldIdentifier, $instance)
                ->update([
                    'identifier' => $newIdentifier,
                    'updated_at' => now(),
                ]);

            return $updated > 0;
        });
    }

    /**
     * Get cart creation timestamp
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        /** @var stdClass|null $cart */
        $cart = $this->baseQuery($identifier, $instance)->first(['created_at']);

        if (! $cart || ! $cart->created_at) {
            return null;
        }

        // Handle both Carbon objects and string timestamps
        return $cart->created_at instanceof DateTimeInterface
            ? $cart->created_at->format('c')
            : (string) $cart->created_at;
    }

    /**
     * Get cart last updated timestamp
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        /** @var stdClass|null $cart */
        $cart = $this->baseQuery($identifier, $instance)->first(['updated_at']);

        if (! $cart || ! $cart->updated_at) {
            return null;
        }

        // Handle both Carbon objects and string timestamps
        return $cart->updated_at instanceof DateTimeInterface
            ? $cart->updated_at->format('c')
            : (string) $cart->updated_at;
    }

    /**
     * Get cart expiration timestamp.
     */
    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        /** @var stdClass|null $cart */
        $cart = $this->baseQuery($identifier, $instance)->first(['expires_at']);

        if (! $cart || ! $cart->expires_at) {
            return null;
        }

        return $cart->expires_at instanceof DateTimeInterface
            ? $cart->expires_at->format('c')
            : (string) $cart->expires_at;
    }

    /**
     * Check if a cart has expired.
     */
    public function isExpired(string $identifier, string $instance): bool
    {
        $expiresAt = $this->getExpiresAt($identifier, $instance);

        if ($expiresAt === null) {
            return false;
        }

        return now()->isAfter($expiresAt);
    }

    // =========================================================================
    // AI & Analytics Methods (Phase 0.2)
    // =========================================================================

    /**
     * Get last activity timestamp for engagement tracking.
     */
    public function getLastActivityAt(string $identifier, string $instance): ?string
    {
        return $this->getTimestampColumn($identifier, $instance, 'last_activity_at');
    }

    /**
     * Update last activity timestamp.
     */
    public function touchLastActivity(string $identifier, string $instance): void
    {
        $this->updateTimestampColumn($identifier, $instance, 'last_activity_at', now()->toDateTimeString());
    }

    /**
     * Get checkout started timestamp.
     */
    public function getCheckoutStartedAt(string $identifier, string $instance): ?string
    {
        return $this->getTimestampColumn($identifier, $instance, 'checkout_started_at');
    }

    /**
     * Mark checkout as started for conversion funnel tracking.
     */
    public function markCheckoutStarted(string $identifier, string $instance): void
    {
        $this->updateTimestampColumn($identifier, $instance, 'checkout_started_at', now()->toDateTimeString());
    }

    /**
     * Get checkout abandoned timestamp.
     */
    public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string
    {
        return $this->getTimestampColumn($identifier, $instance, 'checkout_abandoned_at');
    }

    /**
     * Mark checkout as abandoned for recovery tracking.
     */
    public function markCheckoutAbandoned(string $identifier, string $instance): void
    {
        $this->updateTimestampColumn($identifier, $instance, 'checkout_abandoned_at', now()->toDateTimeString());
    }

    /**
     * Get number of recovery attempts made.
     */
    public function getRecoveryAttempts(string $identifier, string $instance): int
    {
        $attempts = $this->baseQuery($identifier, $instance)->value('recovery_attempts');

        return $attempts !== null ? (int) $attempts : 0;
    }

    /**
     * Increment recovery attempts counter.
     */
    public function incrementRecoveryAttempts(string $identifier, string $instance): void
    {
        $this->baseQuery($identifier, $instance)->increment('recovery_attempts');
    }

    /**
     * Get recovered at timestamp.
     */
    public function getRecoveredAt(string $identifier, string $instance): ?string
    {
        return $this->getTimestampColumn($identifier, $instance, 'recovered_at');
    }

    /**
     * Mark cart as recovered (user returned after abandonment).
     */
    public function markRecovered(string $identifier, string $instance): void
    {
        $this->updateTimestampColumn($identifier, $instance, 'recovered_at', now()->toDateTimeString());
    }

    /**
     * Clear all abandonment tracking data (checkout started, abandoned, recovery).
     */
    public function clearAbandonmentTracking(string $identifier, string $instance): void
    {
        $this->baseQuery($identifier, $instance)->update([
            'checkout_started_at' => null,
            'checkout_abandoned_at' => null,
            'recovery_attempts' => 0,
            'recovered_at' => null,
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // Event Sourcing Methods (Phase 0.3)
    // =========================================================================

    /**
     * Get current event stream position for replay.
     */
    public function getEventStreamPosition(string $identifier, string $instance): int
    {
        $position = $this->baseQuery($identifier, $instance)->value('event_stream_position');

        return $position !== null ? (int) $position : 0;
    }

    /**
     * Update event stream position after recording events.
     */
    public function setEventStreamPosition(string $identifier, string $instance, int $position): void
    {
        $this->baseQuery($identifier, $instance)->update([
            'event_stream_position' => $position,
            'updated_at' => now(),
        ]);
    }

    /**
     * Get aggregate schema version for migrations.
     */
    public function getAggregateVersion(string $identifier, string $instance): string
    {
        $version = $this->baseQuery($identifier, $instance)->value('aggregate_version');

        return $version ?? '1.0';
    }

    /**
     * Update aggregate schema version.
     */
    public function setAggregateVersion(string $identifier, string $instance, string $version): void
    {
        $this->baseQuery($identifier, $instance)->update([
            'aggregate_version' => $version,
            'updated_at' => now(),
        ]);
    }

    /**
     * Get last snapshot timestamp.
     */
    public function getSnapshotAt(string $identifier, string $instance): ?string
    {
        return $this->getTimestampColumn($identifier, $instance, 'snapshot_at');
    }

    /**
     * Update snapshot timestamp after taking a snapshot.
     */
    public function markSnapshotTaken(string $identifier, string $instance): void
    {
        $this->updateTimestampColumn($identifier, $instance, 'snapshot_at', now()->toDateTimeString());
    }

    /**
     * Calculate the expiration timestamp based on TTL.
     */
    private function calculateExpiresAt(): ?string
    {
        if ($this->ttl === null) {
            return null;
        }

        return now()->addSeconds($this->ttl)->toDateTimeString();
    }

    /**
     * Apply lockForUpdate to a query if configured
     */
    private function applyLockForUpdate(Builder $query): Builder
    {
        if (config('cart.database.lock_for_update', false)) {
            return $query->lockForUpdate();
        }

        return $query;
    }

    /**
     * Create a base query with identifier, instance, and optional owner scoping.
     *
     * All database operations should use this method to ensure consistent
     * query building with proper owner isolation when multi-tenancy is enabled.
     */
    private function baseQuery(string $identifier, string $instance): Builder
    {
        $query = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance);

        if ($this->ownerType !== null && $this->ownerId !== null) {
            $query->where('owner_type', $this->ownerType)
                ->where('owner_id', $this->ownerId);
        }

        return $query;
    }

    /**
     * Validate data size to prevent memory issues and DoS attacks
     *
     * @param  array<string, mixed>  $data
     */
    private function validateDataSize(array $data, string $type): void
    {
        // Get size limits from config or use defaults
        $maxItems = config('cart.limits.max_items', 1000);
        $maxDataSize = config('cart.limits.max_data_size_bytes', 1024 * 1024); // 1MB default

        // Check item count limit
        if ($type === 'items' && count($data) > $maxItems) {
            throw new InvalidArgumentException("Cart cannot contain more than {$maxItems} items");
        }

        // Check data size limit
        try {
            $jsonSize = mb_strlen(json_encode($data, JSON_THROW_ON_ERROR));
            if ($jsonSize > $maxDataSize) {
                $maxSizeMB = round($maxDataSize / (1024 * 1024), 2);

                throw new InvalidArgumentException("Cart {$type} data size ({$jsonSize} bytes) exceeds maximum allowed size of {$maxSizeMB}MB");
            }
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Cannot validate {$type} data size: " . $e->getMessage());
        }
    }

    /**
     * Retrieve and decode JSON column data
     *
     * @return array<string, mixed>
     */
    private function getJsonColumn(string $identifier, string $instance, string $column): array
    {
        $result = $this->baseQuery($identifier, $instance)->value($column);

        return $this->decodeData($result, $column, []);
    }

    /**
     * Retrieve a timestamp column value
     */
    private function getTimestampColumn(string $identifier, string $instance, string $column): ?string
    {
        /** @var stdClass|null $cart */
        $cart = $this->baseQuery($identifier, $instance)->first([$column]);

        if (! $cart || ! $cart->{$column}) {
            return null;
        }

        return $cart->{$column} instanceof DateTimeInterface
            ? $cart->{$column}->format('c')
            : (string) $cart->{$column};
    }

    /**
     * Update a timestamp column value
     */
    private function updateTimestampColumn(string $identifier, string $instance, string $column, ?string $value): void
    {
        $this->baseQuery($identifier, $instance)->update([
            $column => $value,
            'updated_at' => now(),
        ]);
    }

    /**
     * Update a single JSON column with CAS
     *
     * @param  array<string, mixed>  $data
     */
    private function updateJsonColumn(string $identifier, string $instance, string $column, array $data, string $operationName): void
    {
        $this->validateDataSize($data, $column);

        // Convert empty arrays to null for better database efficiency
        $jsonData = empty($data) ? null : $this->encodeData($data, $column);

        $this->performCasUpdate($identifier, $instance, [
            $column => $jsonData,
        ], $operationName);
    }

    /**
     * Encode data to JSON with error handling
     *
     * @param  array<string, mixed>  $data
     */
    private function encodeData(array $data, string $type): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Cannot encode {$type} to JSON: " . $e->getMessage());
        }
    }

    /**
     * Decode JSON data with error handling and fallback
     *
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function decodeData(?string $jsonData, string $type, array $fallback = []): array
    {
        if (! $jsonData) {
            return $fallback;
        }

        try {
            return json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            logger()->error("Failed to decode {$type} JSON", [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Perform Compare-And-Swap update with optimistic locking
     *
     * @param  array<string, mixed>  $data
     */
    private function performCasUpdate(string $identifier, string $instance, array $data, string $operationName): void
    {
        $this->database->transaction(function () use ($identifier, $instance, $data, $operationName): void {
            /** @var stdClass|null $current */
            $current = $this->applyLockForUpdate(
                $this->baseQuery($identifier, $instance)
            )->first(['id', 'version']);

            if ($current) {
                $updateData = array_merge($data, [
                    'version' => $current->version + 1,
                    'updated_at' => now(),
                    'expires_at' => $this->calculateExpiresAt(),
                ]);

                $updated = $this->baseQuery($identifier, $instance)
                    ->where('version', $current->version)
                    ->update($updateData);

                if ($updated === 0) {
                    $this->handleCasConflict($identifier, $instance, $current->version, $operationName);
                }
            } else {
                $insertData = array_merge($data, [
                    'id' => Str::uuid(),
                    'identifier' => $identifier,
                    'instance' => $instance,
                    'version' => 1,
                    'expires_at' => $this->calculateExpiresAt(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Include owner columns in insert if set
                if ($this->ownerType !== null && $this->ownerId !== null) {
                    $insertData['owner_type'] = $this->ownerType;
                    $insertData['owner_id'] = $this->ownerId;
                }

                $this->database->table($this->table)->insert($insertData);
            }
        });
    }

    /**
     * Handle CAS conflict by determining current version and throwing appropriate exception
     */
    private function handleCasConflict(string $identifier, string $instance, int $expectedVersion, string $operationName): void
    {
        // Get current version for better error details
        /** @var stdClass|null $currentRecord */
        $currentRecord = $this->baseQuery($identifier, $instance)->first(['version']);

        $currentVersion = $currentRecord ? $currentRecord->version : $expectedVersion + 1;

        throw new CartConflictException(
            "Cart was modified by another request during {$operationName}",
            $expectedVersion,
            $currentVersion
        );
    }
}
