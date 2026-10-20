<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Support\CartLimits;
use AIArmada\Cart\Support\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface as Database;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
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

    public function backingKey(): string
    {
        return spl_object_id($this->database) . ':' . $this->table;
    }

    /**
     * Resolve the identifier and instance for a cart row by its UUID.
     *
     * @return object{identifier: string, instance: string}|null
     */
    public function findSnapshotById(string $uuid): ?object
    {
        $query = $this->database->table($this->table)->where('id', $uuid);

        $this->applyOwnerConstraints($query);

        /** @var object{identifier: string, instance: string}|null $snapshot */
        $snapshot = $query->first(['identifier', 'instance']);

        return $snapshot;
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
     * Read the complete cart content together with the revision it was read at.
     *
     * Item/condition/metadata content and the optimistic-locking revision are
     * read in a single query so a replacement computed from them can be
     * verified against exactly the state it was derived from.
     */
    public function getSnapshot(string $identifier, string $instance): ?CartStorageSnapshot
    {
        /** @var stdClass|null $row */
        $row = $this->baseQuery($identifier, $instance)->first(['id', 'version', 'items', 'conditions', 'metadata']);

        if ($row === null) {
            return null;
        }

        return new CartStorageSnapshot(
            id: (string) $row->id,
            version: (int) $row->version,
            items: $this->decodeData($row->items, 'items'),
            conditions: $this->decodeData($row->conditions, 'conditions'),
            metadata: $this->decodeData($row->metadata, 'metadata'),
        );
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
     * Atomically replace cart items at the expected revision.
     *
     * @param  array<string, mixed>  $items
     */
    public function putItems(string $identifier, string $instance, array $items, CartRevision $expectedRevision): CartRevision
    {
        $this->validateDataSize($items, 'items');

        // Convert empty arrays to null for better database efficiency
        return $this->replace($identifier, $instance, [
            'items' => empty($items) ? null : $this->encodeData($items, 'items'),
        ], 'items update', $expectedRevision);
    }

    /**
     * Atomically replace cart conditions at the expected revision.
     *
     * @param  array<string, mixed>  $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions, CartRevision $expectedRevision): CartRevision
    {
        $this->validateDataSize($conditions, 'conditions');

        // Convert empty arrays to null for better database efficiency
        return $this->replace($identifier, $instance, [
            'conditions' => empty($conditions) ? null : $this->encodeData($conditions, 'conditions'),
        ], 'conditions update', $expectedRevision);
    }

    /**
     * Atomically replace both items and conditions at the expected revision.
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions, CartRevision $expectedRevision): CartRevision
    {
        $this->validateDataSize($items, 'items');
        $this->validateDataSize($conditions, 'conditions');

        // Items should always have data when using putBoth, but conditions might be empty
        $itemsJson = $this->encodeData($items, 'items');
        $conditionsJson = empty($conditions) ? null : $this->encodeData($conditions, 'conditions');

        return $this->replace($identifier, $instance, [
            'items' => $itemsJson,
            'conditions' => $conditionsJson,
        ], 'both items and conditions update', $expectedRevision);
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
        if (! app()->environment(['testing', 'local'])) {
            throw new RuntimeException('Flush operation is only allowed in testing and local environments');
        }

        // Always delete through the owner predicate (global-only rows when no
        // owner is set). Never truncate: in a global context that would wipe
        // every owner's carts.
        $query = $this->database->table($this->table);
        CartOwnerScope::applyForOwner($query, $this->ownerType, $this->ownerId);
        $query->delete();
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

        $this->applyOwnerConstraints($query);

        return $query->pluck('instance')->toArray();
    }

    /**
     * Remove all instances for a specific identifier
     */
    public function forgetIdentifier(string $identifier): void
    {
        $query = $this->database->table($this->table)
            ->where('identifier', $identifier);

        $this->applyOwnerConstraints($query);

        $query->delete();
    }

    /**
     * Store cart metadata (atomic merge owned by the storage).
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->mutateMetadata($identifier, $instance, $key, static fn (): mixed => $value);
    }

    /**
     * Atomically modify one metadata key from its fresh current value.
     */
    public function mutateMetadata(string $identifier, string $instance, string $key, Closure $compute): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            $snapshot = $this->getSnapshot($identifier, $instance);
            $metadata = $snapshot?->metadata ?? [];

            $newValue = $compute($metadata[$key] ?? null);

            if ($newValue === null) {
                unset($metadata[$key]);
            } else {
                $metadata[$key] = $newValue;
            }

            $metadata = array_filter($metadata, fn ($value) => $value !== null);
            $this->validateDataSize($metadata, 'metadata');

            if ($snapshot !== null && $metadata === $snapshot->metadata) {
                return $newValue;
            }

            if ($snapshot === null && $metadata === []) {
                return $newValue;
            }

            try {
                $this->replace($identifier, $instance, [
                    'metadata' => empty($metadata) ? null : $this->encodeData($metadata, 'metadata'),
                ], 'metadata update', $snapshot?->revision() ?? CartRevision::none());

                return $newValue;
            } catch (CartConflictException $exception) {
                if ($attempt >= self::MAX_METADATA_ATTEMPTS) {
                    throw $exception;
                }

                $this->backOffBeforeCasRetry($attempt);
            }
        }
    }

    /**
     * Store multiple metadata values at once (atomic merge owned by the storage).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            $snapshot = $this->getSnapshot($identifier, $instance);

            $merged = array_merge($snapshot?->metadata ?? [], $metadata);

            // Filter out null values and convert empty metadata to null
            $merged = array_filter($merged, fn ($value) => $value !== null);
            $this->validateDataSize($merged, 'metadata');

            if ($snapshot !== null && $merged === $snapshot->metadata) {
                return;
            }

            if ($snapshot === null && $merged === []) {
                return;
            }

            try {
                $this->replace($identifier, $instance, [
                    'metadata' => empty($merged) ? null : $this->encodeData($merged, 'metadata'),
                ], 'metadata batch update', $snapshot?->revision() ?? CartRevision::none());

                return;
            } catch (CartConflictException $exception) {
                if ($attempt >= self::MAX_METADATA_ATTEMPTS) {
                    throw $exception;
                }

                $this->backOffBeforeCasRetry($attempt);
            }
        }
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
     * Clear all metadata for a cart (blind write keyed by identifier).
     */
    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->upsertWrite($identifier, $instance, [
            'metadata' => null,
        ], 'metadata clear');
    }

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation.
     */
    public function clearAll(string $identifier, string $instance): void
    {
        $this->upsertWrite($identifier, $instance, [
            'items' => json_encode([]),
            'conditions' => null,
            'metadata' => null,
        ], 'clear all');
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
     *
     * The swap is refused (returns false, both carts untouched) when the target
     * identifier already holds a cart: overwriting it would silently destroy the
     * target's items, conditions, and metadata. Callers that need merge behavior
     * must use MigrateGuestCartToUserAction instead.
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        if ($oldIdentifier === $newIdentifier) {
            return $this->has($oldIdentifier, $instance);
        }

        // Existence checks run inside the transaction with row locks so a
        // concurrent swap into the same target cannot slip past the check.
        return $this->database->transaction(function () use ($oldIdentifier, $newIdentifier, $instance) {
            $source = $this->applyLockForUpdate(
                $this->baseQuery($oldIdentifier, $instance)
            )->first(['id', 'version']);

            if ($source === null) {
                return false;
            }

            $targetExists = $this->applyLockForUpdate(
                $this->baseQuery($newIdentifier, $instance)
            )->exists();

            if ($targetExists) {
                return false;
            }

            $updated = $this->baseQuery($oldIdentifier, $instance)
                ->where('id', $source->id)
                ->update([
                    'identifier' => $newIdentifier,
                    'version' => $source->version + 1,
                    'updated_at' => CarbonImmutable::now(),
                ]);

            return $updated > 0;
        });
    }

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
     * @throws CartConflictException When the source revision no longer matches or was already consumed
     */
    public function consumeForMerge(string $identifier, string $instance, CartRevision $expectedRevision, string $mergedIntoId): CartRevision
    {
        if ($expectedRevision->isCreation()) {
            throw new InvalidArgumentException('A merge consumption requires an existing source revision.');
        }

        $now = CarbonImmutable::now();

        $updated = $this->baseQuery($identifier, $instance)
            ->where('id', $expectedRevision->cartId)
            ->where('version', $expectedRevision->version)
            ->whereNull('merged_into_id')
            ->update([
                'items' => null,
                'conditions' => null,
                'metadata' => null,
                'merged_into_id' => $mergedIntoId,
                'expired_at' => $now,
                'expires_at' => $this->calculateExpiresAt(),
                'version' => $expectedRevision->version + 1,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            throw $this->buildConflictException($identifier, $instance, $expectedRevision, 'merge consumption');
        }

        return CartRevision::of($expectedRevision->cartId, $expectedRevision->version + 1);
    }

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
    ): CartRevision {
        if (! $sourceStorage instanceof self) {
            throw new InvalidArgumentException('Committing a merge transfer requires a compatible storage backend.');
        }

        if ($sourceStorage->backingKey() !== $this->backingKey()) {
            throw new InvalidArgumentException('Source and target storage do not share a backing store.');
        }

        return $this->database->transaction(function () use ($sourceStorage, $sourceIdentifier, $targetIdentifier, $instance, $items, $conditions, $metadata, $sourceRevision, $targetRevision): CartRevision {
            $revision = $this->putItems($targetIdentifier, $instance, $items, $targetRevision);

            if ($conditions !== []) {
                $revision = $this->putConditions($targetIdentifier, $instance, $conditions, $revision);
            }

            if ($metadata !== []) {
                $this->putMetadataBatch($targetIdentifier, $instance, $metadata);
            }

            $targetCartId = (string) $revision->cartId;

            return $sourceStorage->consumeForMerge($sourceIdentifier, $instance, $sourceRevision, $targetCartId);
        });
    }

    /**
     * Remove a cart only while the expected revision is still current.
     *
     * A rejected guard returns false and leaves the newer write untouched.
     *
     * @throws InvalidArgumentException When the expected revision is a creation
     */
    public function forgetIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool
    {
        if ($expectedRevision->isCreation()) {
            throw new InvalidArgumentException('A guarded cart removal requires an existing revision.');
        }

        $deleted = $this->baseQuery($identifier, $instance)
            ->where('id', $expectedRevision->cartId)
            ->where('version', $expectedRevision->version)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Clear a cart only while the expected revision is still current.
     *
     * A rejected guard returns false and leaves the newer write untouched.
     *
     * @throws InvalidArgumentException When the expected revision is a creation
     */
    public function clearAllIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool
    {
        if ($expectedRevision->isCreation()) {
            throw new InvalidArgumentException('A guarded cart clear requires an existing revision.');
        }

        $updated = $this->baseQuery($identifier, $instance)
            ->where('id', $expectedRevision->cartId)
            ->where('version', $expectedRevision->version)
            ->update([
                'items' => json_encode([]),
                'conditions' => null,
                'metadata' => null,
                'version' => $expectedRevision->version + 1,
                'updated_at' => CarbonImmutable::now(),
                'expires_at' => $this->calculateExpiresAt(),
            ]);

        return $updated > 0;
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

        return CarbonImmutable::now()->isAfter($expiresAt);
    }

    /**
     * Calculate the expiration timestamp based on TTL.
     */
    private function calculateExpiresAt(): ?string
    {
        if ($this->ttl === null) {
            return null;
        }

        return CarbonImmutable::now()->addSeconds($this->ttl)->toDateTimeString();
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

        $this->applyOwnerConstraints($query);

        return $query;
    }

    /**
     * Apply owner constraints to a query.
     *
     * When no owner is set, operations are scoped to global carts only.
     */
    private function applyOwnerConstraints(Builder $query): void
    {
        CartOwnerScope::applyForOwner($query, $this->ownerType, $this->ownerId);
    }

    /**
     * Resolve the internal uniqueness key for the current owner scope.
     */
    private function resolveOwnerScope(): string
    {
        return OwnerScopeKey::forTypeAndId($this->ownerType, $this->ownerId);
    }

    /**
     * Validate data size to prevent memory issues and DoS attacks
     *
     * @param  array<string, mixed>  $data
     */
    private function validateDataSize(array $data, string $type): void
    {
        // Get size limits from config or use defaults
        $limits = CartLimits::fromConfig();
        $maxItems = $limits->maxItems;
        $maxDataSize = $limits->maxDataSizeBytes;

        // Check item count limit
        if ($type === 'items' && count($data) > $maxItems) {
            throw new InvalidArgumentException("Cart cannot contain more than {$maxItems} items");
        }

        // Validate serializability before checking size
        $this->validateSerializable($data, $type);

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
     * Maximum nesting depth accepted in stored cart payloads.
     */
    private const MAX_NESTING_DEPTH = 32;

    /**
     * Validate that all values in an array are JSON-serializable.
     *
     * Prevents storing closures, resources, or other non-serializable types
     * that would fail silently during json_encode.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $type  The data type being validated (for error messages)
     * @param  string  $path  The current path in the data structure (for nested arrays)
     *
     * @throws InvalidArgumentException When non-serializable values are found
     */
    private function validateSerializable(array $data, string $type, string $path = '', int $depth = 0): void
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new InvalidArgumentException(
                "Cart {$type} exceeds the maximum nesting depth of " . self::MAX_NESTING_DEPTH . " at '{$path}'."
            );
        }

        foreach ($data as $key => $value) {
            $currentPath = $path === '' ? (string) $key : "{$path}.{$key}";

            if ($value === null || is_scalar($value)) {
                continue;
            }

            if (is_array($value)) {
                $this->validateSerializable($value, $type, $currentPath, $depth + 1);

                continue;
            }

            if (is_object($value)) {
                // Allow objects that implement JsonSerializable or have toArray/toJson methods
                if ($value instanceof JsonSerializable) {
                    continue;
                }

                if (method_exists($value, 'toArray') || method_exists($value, 'toJson')) {
                    continue;
                }

                // Reject closures explicitly
                if ($value instanceof Closure) {
                    throw new InvalidArgumentException(
                        "Cart {$type} contains a Closure at '{$currentPath}' which cannot be serialized. " .
                        'Use a factory key pattern or convert closures to serializable data before storage.'
                    );
                }

                // Reject other non-serializable objects
                throw new InvalidArgumentException(
                    sprintf(
                        "Cart %s contains non-serializable object of type '%s' at '%s'. " .
                        'Objects must implement JsonSerializable or have toArray()/toJson() methods.',
                        $type,
                        $value::class,
                        $currentPath
                    )
                );
            }

            if (is_resource($value)) {
                throw new InvalidArgumentException(
                    "Cart {$type} contains a resource at '{$currentPath}' which cannot be serialized."
                );
            }
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
            $decoded = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                logger()->error("Failed to decode {$type} JSON", [
                    'type' => $type,
                    'error' => 'Decoded value is not an array.',
                ]);

                return $fallback;
            }

            return $decoded;
        } catch (JsonException $e) {
            logger()->error("Failed to decode {$type} JSON", [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Maximum number of times a storage-owned metadata merge re-reads fresh
     * content before surfacing the conflict to the caller.
     */
    private const MAX_METADATA_ATTEMPTS = 5;

    /**
     * Atomically replace a cart row at the expected revision.
     *
     * An expected creation ({@see CartRevision::none()}) inserts a new row and
     * conflicts when one already exists. An expected row update is guarded by
     * both the row UUID and the version, so a snapshot taken before the row
     * was deleted and recreated can never overwrite the replacement row.
     *
     * @param  array<string, mixed>  $data  Raw column payload (already encoded)
     *
     * @throws CartConflictException When the expected revision no longer matches
     */
    private function replace(string $identifier, string $instance, array $data, string $operationName, CartRevision $expectedRevision): CartRevision
    {
        $ownerColumns = $this->ownerTupleColumns();
        $now = CarbonImmutable::now();

        if ($expectedRevision->isCreation()) {
            $insertData = array_merge($data, [
                'id' => Str::uuid(),
                'identifier' => $identifier,
                'instance' => $instance,
                $ownerColumns->ownerTypeColumn => $this->ownerType,
                $ownerColumns->ownerIdColumn => $this->ownerId !== null ? (string) $this->ownerId : null,
                'owner_scope' => $this->resolveOwnerScope(),
                'version' => 1,
                'expires_at' => $this->calculateExpiresAt(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                // Run the insert inside a savepoint so a unique-key violation
                // inside a surrounding transaction (for example a migration
                // merge) aborts only the savepoint. The connection stays
                // usable for the conflict diagnostics below and for the
                // caller's retry, which PostgreSQL would otherwise reject
                // with "current transaction is aborted".
                $this->database->transaction(function () use ($insertData): void {
                    $this->database->table($this->table)->insert($insertData);
                });
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                throw $this->buildConflictException($identifier, $instance, $expectedRevision, $operationName);
            }

            return CartRevision::of((string) $insertData['id'], 1);
        }

        $updated = $this->baseQuery($identifier, $instance)
            ->where('id', $expectedRevision->cartId)
            ->where('version', $expectedRevision->version)
            ->update(array_merge($data, [
                $ownerColumns->ownerTypeColumn => $this->ownerType,
                $ownerColumns->ownerIdColumn => $this->ownerId !== null ? (string) $this->ownerId : null,
                'owner_scope' => $this->resolveOwnerScope(),
                'version' => $expectedRevision->version + 1,
                'updated_at' => $now,
                'expires_at' => $this->calculateExpiresAt(),
            ]));

        if ($updated === 0) {
            throw $this->buildConflictException($identifier, $instance, $expectedRevision, $operationName);
        }

        return CartRevision::of($expectedRevision->cartId, $expectedRevision->version + 1);
    }

    /**
     * Blind upsert write: no expected revision, keyed only by the
     * identifier/instance/owner scope.
     *
     * Used by clear operations whose payload is a fixed reset rather than a
     * value computed from a read. The attempt is retried once with backoff
     * when it loses a race: either a concurrent first-write won the unique
     * key (the retry then takes the version-checked update path) or a
     * concurrent update moved the version.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertWrite(string $identifier, string $instance, array $data, string $operationName): CartRevision
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return $this->database->transaction(function () use ($identifier, $instance, $data, $operationName): CartRevision {
                    $ownerColumns = $this->ownerTupleColumns();

                    /** @var stdClass|null $current */
                    $current = $this->applyLockForUpdate(
                        $this->baseQuery($identifier, $instance)
                    )->first(['id', 'version']);

                    if ($current) {
                        $updateData = array_merge($data, [
                            $ownerColumns->ownerTypeColumn => $this->ownerType,
                            $ownerColumns->ownerIdColumn => $this->ownerId !== null ? (string) $this->ownerId : null,
                            'owner_scope' => $this->resolveOwnerScope(),
                            'version' => $current->version + 1,
                            'updated_at' => CarbonImmutable::now(),
                            'expires_at' => $this->calculateExpiresAt(),
                        ]);

                        $updated = $this->baseQuery($identifier, $instance)
                            ->where('id', $current->id)
                            ->where('version', $current->version)
                            ->update($updateData);

                        if ($updated === 0) {
                            throw $this->buildConflictException(
                                $identifier,
                                $instance,
                                CartRevision::of((string) $current->id, (int) $current->version),
                                $operationName
                            );
                        }

                        return CartRevision::of((string) $current->id, (int) $current->version + 1);
                    }

                    $insertData = array_merge($data, [
                        'id' => Str::uuid(),
                        'identifier' => $identifier,
                        'instance' => $instance,
                        $ownerColumns->ownerTypeColumn => $this->ownerType,
                        $ownerColumns->ownerIdColumn => $this->ownerId !== null ? (string) $this->ownerId : null,
                        'owner_scope' => $this->resolveOwnerScope(),
                        'version' => 1,
                        'expires_at' => $this->calculateExpiresAt(),
                        'created_at' => CarbonImmutable::now(),
                        'updated_at' => CarbonImmutable::now(),
                    ]);

                    $this->database->table($this->table)->insert($insertData);

                    return CartRevision::of((string) $insertData['id'], 1);
                });
            } catch (CartConflictException $exception) {
                if ($attempts >= 2) {
                    throw $exception;
                }

                $this->backOffBeforeCasRetry($attempts);
            } catch (QueryException $exception) {
                if ($attempts >= 2 || ! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                $this->backOffBeforeCasRetry($attempts);
            }
        }
    }

    /**
     * Brief backoff before a CAS retry so racing writers do not spin in lockstep.
     */
    private function backOffBeforeCasRetry(int $attempt): void
    {
        usleep(25000 * $attempt);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($code, ['23000', '23505'], true);
    }

    /**
     * Build a conflict exception describing the expectation and the current
     * stored revision (0 when no cart row currently exists).
     */
    private function buildConflictException(string $identifier, string $instance, CartRevision $expectedRevision, string $operationName): CartConflictException
    {
        $currentVersion = $this->baseQuery($identifier, $instance)->value('version');

        return new CartConflictException(
            "Cart was modified by another request during {$operationName}",
            $expectedRevision->version ?? 0,
            $currentVersion !== null ? (int) $currentVersion : 0
        );
    }

    private function ownerTupleColumns(): OwnerTupleColumns
    {
        return OwnerTupleColumns::forModelClass(CartModel::class);
    }
}
