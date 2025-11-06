<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use AIArmada\Cart\Exceptions\CartConflictException;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface as Database;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

final readonly class DatabaseStorage implements StorageInterface
{
    public function __construct(
        private Database $database,
        private string $table = 'carts'
    ) {
        //
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
        return $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->exists();
    }

    /**
     * Remove cart from storage
     */
    public function forget(string $identifier, string $instance): void
    {
        $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->delete();
    }

    /**
     * Clear all carts from storage
     * WARNING: This is a dangerous operation that should be used with extreme caution
     */
    public function flush(): void
    {
        // Only allow flush in testing environments to prevent accidental data loss
        if (app()->environment(['testing', 'local'])) {
            $this->database->table($this->table)->truncate();
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
        return $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->pluck('instance')
            ->toArray();
    }

    /**
     * Remove all instances for a specific identifier
     */
    public function forgetIdentifier(string $identifier): void
    {
        $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->delete();
    }

    /**
     * Store cart metadata
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->database->transaction(function () use ($identifier, $instance, $key, $value): void {
            // Get existing metadata
            $existing = $this->database->table($this->table)
                ->where('identifier', $identifier)
                ->where('instance', $instance)
                ->value('metadata');

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
            $existing = $this->database->table($this->table)
                ->where('identifier', $identifier)
                ->where('instance', $instance)
                ->value('metadata');

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
        $result = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->value('metadata');

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
        $result = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->value('metadata');

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
     * Get cart version for change tracking
     */
    public function getVersion(string $identifier, string $instance): ?int
    {
        $version = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->value('version');

        return $version !== null ? (int) $version : null;
    }

    /**
     * Get cart ID (primary key) from storage
     */
    public function getId(string $identifier, string $instance): ?string
    {
        return $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->value('id');
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
            $this->database->table($this->table)
                ->where('identifier', $newIdentifier)
                ->where('instance', $instance)
                ->delete();

            // Now update the source cart to use the new identifier
            $updated = $this->database->table($this->table)
                ->where('identifier', $oldIdentifier)
                ->where('instance', $instance)
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
        $cart = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->first(['created_at']);

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
        $cart = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->first(['updated_at']);

        if (! $cart || ! $cart->updated_at) {
            return null;
        }

        // Handle both Carbon objects and string timestamps
        return $cart->updated_at instanceof DateTimeInterface
            ? $cart->updated_at->format('c')
            : (string) $cart->updated_at;
    }

    /**
     * Apply lockForUpdate to a query if configured
     */
    private function applyLockForUpdate(\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Query\Builder
    {
        if (config('cart.database.lock_for_update', false)) {
            return $query->lockForUpdate();
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
            throw new InvalidArgumentException("Cannot validate {$type} data size: ".$e->getMessage());
        }
    }

    /**
     * Retrieve and decode JSON column data
     *
     * @return array<string, mixed>
     */
    private function getJsonColumn(string $identifier, string $instance, string $column): array
    {
        $result = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->value($column);

        return $this->decodeData($result, $column, []);
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
            throw new InvalidArgumentException("Cannot encode {$type} to JSON: ".$e->getMessage());
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
                $this->database->table($this->table)
                    ->where('identifier', $identifier)
                    ->where('instance', $instance)
            )->first(['id', 'version']);

            if ($current) {
                $updateData = array_merge($data, [
                    'version' => $current->version + 1,
                    'updated_at' => now(),
                ]);

                $updated = $this->database->table($this->table)
                    ->where('identifier', $identifier)
                    ->where('instance', $instance)
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
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

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
        $currentRecord = $this->database->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->first(['version']);

        $currentVersion = $currentRecord ? $currentRecord->version : $expectedVersion + 1;

        throw new CartConflictException(
            "Cart was modified by another request during {$operationName}",
            $expectedVersion,
            $currentVersion
        );
    }
}
