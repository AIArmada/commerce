<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use Illuminate\Cache\Repository as Cache;
use InvalidArgumentException;
use JsonException;

final readonly class CacheStorage implements StorageInterface
{
    public function __construct(
        private Cache $cache,
        private string $keyPrefix = 'cart',
        private int $ttl = 86400, // 24 hours
        private bool $useLocking = false, // Enable for multi-server setups with shared cache
        private int $lockTimeout = 5 // Lock timeout in seconds
    ) {
        //
    }

    /**
     * Check if cart exists in storage
     */
    public function has(string $identifier, string $instance): bool
    {
        // Check if either items or conditions exist for this cart
        return $this->cache->has($this->getItemsKey($identifier, $instance))
            || $this->cache->has($this->getConditionsKey($identifier, $instance));
    }

    /**
     * Remove cart from storage
     */
    public function forget(string $identifier, string $instance): void
    {
        $this->cache->forget($this->getItemsKey($identifier, $instance));
        $this->cache->forget($this->getConditionsKey($identifier, $instance));
    }

    /**
     * Clear all carts from storage
     */
    public function flush(): void
    {
        // For cache storage, we'll clear all items
        // In production you might want to use cache tags for more granular control
        $store = $this->cache->getStore();
        if (method_exists($store, 'flush')) { // @phpstan-ignore function.alreadyNarrowedType
            $store->flush();
        }
    }

    /**
     * Get all instances for a specific identifier
     *
     * @return array<string, mixed>
     */
    public function getInstances(string $identifier): array
    {
        // This is a limitation of cache storage - we can't easily list keys
        // In production, you might want to maintain a separate index
        // For now, return empty array
        return [];
    }

    /**
     * Remove all instances for a specific identifier
     */
    public function forgetIdentifier(string $identifier): void
    {
        // This is a limitation of cache storage - we can't easily list keys
        // In production, you might want to maintain a separate index
        // For now, we can't efficiently remove all instances for an identifier
    }

    /**
     * Retrieve cart items from storage
     *
     * @return array<string, mixed>
     */
    public function getItems(string $identifier, string $instance): array
    {
        $data = $this->cache->get($this->getItemsKey($identifier, $instance));

        if (is_string($data)) {
            return json_decode($data, true) ?: [];
        }

        return $data ?: [];
    }

    /**
     * Retrieve cart conditions from storage
     *
     * @return array<string, mixed>
     */
    public function getConditions(string $identifier, string $instance): array
    {
        $data = $this->cache->get($this->getConditionsKey($identifier, $instance));

        if (is_string($data)) {
            return json_decode($data, true) ?: [];
        }

        return $data ?: [];
    }

    /**
     * Store cart items in storage
     *
     * @param  array<string, mixed>  $items
     */
    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->validateDataSize($items, 'items');

        if ($this->useLocking) {
            $this->putItemsWithLock($identifier, $instance, $items);
        } else {
            $this->putItemsSimple($identifier, $instance, $items);
        }
    }

    /**
     * Store cart conditions in storage
     *
     * @param  array<string, mixed>  $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->validateDataSize($conditions, 'conditions');

        if ($this->useLocking) {
            $this->putConditionsWithLock($identifier, $instance, $conditions);
        } else {
            $this->putConditionsSimple($identifier, $instance, $conditions);
        }
    }

    /**
     * Store both items and conditions in storage
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->putItems($identifier, $instance, $items);
        $this->putConditions($identifier, $instance, $conditions);
    }

    /**
     * Store cart metadata
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);

        if ($this->useLocking && method_exists($this->cache, 'lock')) {
            $this->putMetadataWithLock($metadataKey, $value);
        } else {
            $this->cache->put($metadataKey, $value, $this->ttl);
        }

        // Track metadata key in registry for clearMetadata support
        $keysRegistryKey = "{$this->keyPrefix}.{$identifier}.{$instance}.metadata._keys";
        $metadataKeys = $this->cache->get($keysRegistryKey, []);
        if (! in_array($key, $metadataKeys, true)) {
            $metadataKeys[] = $key;
            $this->cache->put($keysRegistryKey, $metadataKeys, $this->ttl);
        }
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

        $keysRegistryKey = "{$this->keyPrefix}.{$identifier}.{$instance}.metadata._keys";

        if ($this->useLocking && method_exists($this->cache, 'lock')) {
            $lock = $this->cache->lock("{$this->keyPrefix}.lock.{$identifier}.{$instance}.metadata", 10);

            try {
                $lock->block(5);

                // Store all metadata values
                foreach ($metadata as $key => $value) {
                    $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
                    $this->cache->put($metadataKey, $value, $this->ttl);
                }

                // Update registry with all new keys
                $metadataKeys = $this->cache->get($keysRegistryKey, []);
                $newKeys = array_unique(array_merge($metadataKeys, array_keys($metadata)));
                $this->cache->put($keysRegistryKey, $newKeys, $this->ttl);
            } finally {
                $lock->release();
            }
        } else {
            // Store all metadata values without locking
            foreach ($metadata as $key => $value) {
                $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
                $this->cache->put($metadataKey, $value, $this->ttl);
            }

            // Update registry with all new keys
            $metadataKeys = $this->cache->get($keysRegistryKey, []);
            $newKeys = array_unique(array_merge($metadataKeys, array_keys($metadata)));
            $this->cache->put($keysRegistryKey, $newKeys, $this->ttl);
        }
    }

    /**
     * Retrieve cart metadata
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);

        return $this->cache->get($metadataKey);
    }

    /**
     * Retrieve all cart metadata
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $keysRegistryKey = "{$this->keyPrefix}.{$identifier}.{$instance}.metadata._keys";
        $metadataKeys = $this->cache->get($keysRegistryKey, []);
        $metadata = [];

        foreach ($metadataKeys as $key) {
            $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
            $value = $this->cache->get($metadataKey);
            if ($value !== null) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * Clear all metadata for a cart
     */
    public function clearMetadata(string $identifier, string $instance): void
    {
        $keysRegistryKey = "{$this->keyPrefix}.{$identifier}.{$instance}.metadata._keys";
        $metadataKeys = $this->cache->get($keysRegistryKey, []);

        foreach ($metadataKeys as $key) {
            $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
            $this->cache->forget($metadataKey);
        }

        $this->cache->forget($keysRegistryKey);
    }

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation
     */
    public function clearAll(string $identifier, string $instance): void
    {
        // Clear items and conditions
        $this->cache->put($this->getItemsKey($identifier, $instance), [], $this->ttl);
        $this->cache->put($this->getConditionsKey($identifier, $instance), [], $this->ttl);

        // Clear metadata
        $this->clearMetadata($identifier, $instance);
    }

    /**
     * Swap cart identifier by transferring cart data from old identifier to new identifier.
     * This changes cart ownership to ensure the new identifier has an active cart.
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        // Check if source cart exists
        if (! $this->has($oldIdentifier, $instance)) {
            return false;
        }

        // Get all data from the source identifier
        $items = $this->getItems($oldIdentifier, $instance);
        $conditions = $this->getConditions($oldIdentifier, $instance);

        // Transfer source cart to new identifier (swap even if empty to ensure ownership)
        $this->putBoth($newIdentifier, $instance, $items, $conditions);

        // Remove data from old identifier
        $this->forget($oldIdentifier, $instance);

        return true;
    }

    /**
     * Get cart version for change tracking
     * Cache storage doesn't support versioning, returns null
     */
    public function getVersion(string $identifier, string $instance): ?int
    {
        return null;
    }

    /**
     * Get cart ID (primary key) from storage
     * Cache storage doesn't have IDs, returns null
     */
    public function getId(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Get cart creation timestamp (not supported by cache storage)
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Get cart last updated timestamp (not supported by cache storage)
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Store items with locking to prevent concurrent modification
     *
     * @param  array<string, mixed>  $items
     */
    private function putItemsWithLock(string $identifier, string $instance, array $items): void
    {
        $key = $this->getItemsKey($identifier, $instance);

        if (! method_exists($this->cache, 'lock')) {
            // Fallback for cache drivers that don't support locking
            $this->cache->put($key, $items, $this->ttl);

            return;
        }

        $lock = $this->cache->lock("lock.{$key}", $this->lockTimeout);

        $lock->block($this->lockTimeout, function () use ($key, $items): void {
            $this->cache->put($key, $items, $this->ttl);
        });
    }

    /**
     * Store items without locking (simple/fast mode)
     *
     * @param  array<string, mixed>  $items
     */
    private function putItemsSimple(string $identifier, string $instance, array $items): void
    {
        $this->cache->put($this->getItemsKey($identifier, $instance), $items, $this->ttl);
    }

    /**
     * Store conditions with locking to prevent concurrent modification
     *
     * @param  array<string, mixed>  $conditions
     */
    private function putConditionsWithLock(string $identifier, string $instance, array $conditions): void
    {
        $key = $this->getConditionsKey($identifier, $instance);

        if (! method_exists($this->cache, 'lock')) {
            // Fallback for cache drivers that don't support locking
            $this->cache->put($key, $conditions, $this->ttl);

            return;
        }

        $lock = $this->cache->lock("lock.{$key}", $this->lockTimeout);

        $lock->block($this->lockTimeout, function () use ($key, $conditions): void {
            $this->cache->put($key, $conditions, $this->ttl);
        });
    }

    /**
     * Store conditions without locking (simple/fast mode)
     *
     * @param  array<string, mixed>  $conditions
     */
    private function putConditionsSimple(string $identifier, string $instance, array $conditions): void
    {
        $this->cache->put($this->getConditionsKey($identifier, $instance), $conditions, $this->ttl);
    }

    /**
     * Store metadata with locking to prevent concurrent modification
     */
    private function putMetadataWithLock(string $metadataKey, mixed $value): void
    {
        if (! method_exists($this->cache, 'lock')) {
            $this->cache->put($metadataKey, $value, $this->ttl);

            return;
        }

        $lock = $this->cache->lock("lock.{$metadataKey}", $this->lockTimeout);

        $lock->block($this->lockTimeout, function () use ($metadataKey, $value): void {
            $this->cache->put($metadataKey, $value, $this->ttl);
        });
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
     * Get the items storage key
     */
    private function getItemsKey(string $identifier, string $instance): string
    {
        return "{$this->keyPrefix}.{$identifier}.{$instance}.items";
    }

    /**
     * Get the conditions storage key
     */
    private function getConditionsKey(string $identifier, string $instance): string
    {
        return "{$this->keyPrefix}.{$identifier}.{$instance}.conditions";
    }

    /**
     * Get the metadata storage key
     */
    private function getMetadataKey(string $identifier, string $instance, string $key): string
    {
        return "{$this->keyPrefix}.{$identifier}.{$instance}.metadata.{$key}";
    }
}
