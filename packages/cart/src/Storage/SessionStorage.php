<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use Illuminate\Contracts\Session\Session;
use InvalidArgumentException;
use JsonException;

final readonly class SessionStorage implements StorageInterface
{
    public function __construct(
        private Session $session,
        private string $keyPrefix = 'cart'
    ) {
        //
    }

    /**
     * Check if cart exists in storage
     */
    public function has(string $identifier, string $instance): bool
    {
        $cartData = $this->session->get($this->keyPrefix, []);

        return isset($cartData[$identifier][$instance]['items']) ||
               isset($cartData[$identifier][$instance]['conditions']);
    }

    /**
     * Remove cart from storage
     */
    public function forget(string $identifier, string $instance): void
    {
        $cartData = $this->session->get($this->keyPrefix, []);

        if (isset($cartData[$identifier][$instance])) {
            unset($cartData[$identifier][$instance]);

            // If this identifier has no more instances, remove it entirely
            if (empty($cartData[$identifier])) {
                unset($cartData[$identifier]);
            }

            // Update the session with the modified data
            if (empty($cartData)) {
                $this->session->forget($this->keyPrefix);
            } else {
                $this->session->put($this->keyPrefix, $cartData);
            }
        }
    }

    /**
     * Clear all carts from storage
     */
    public function flush(): void
    {
        // Remove the entire cart data from session
        $this->session->forget($this->keyPrefix);
    }

    /**
     * Get all instances for a specific identifier
     *
     * @return array<string>
     */
    public function getInstances(string $identifier): array
    {
        // Get the nested cart data for this identifier
        $cartData = $this->session->get($this->keyPrefix, []);

        if (! isset($cartData[$identifier]) || ! is_array($cartData[$identifier])) {
            return [];
        }

        // Return all instance names (keys) for this identifier
        return array_keys($cartData[$identifier]);
    }

    /**
     * Remove all instances for a specific identifier
     */
    public function forgetIdentifier(string $identifier): void
    {
        // Get current cart data
        $cartData = $this->session->get($this->keyPrefix, []);

        // Remove this identifier's data
        unset($cartData[$identifier]);

        // Put back the modified cart data
        if (empty($cartData)) {
            $this->session->forget($this->keyPrefix);
        } else {
            $this->session->put($this->keyPrefix, $cartData);
        }
    }

    /**
     * Retrieve cart items from storage
     *
     * @return array<string, mixed>
     */
    public function getItems(string $identifier, string $instance): array
    {
        $data = $this->session->get($this->getItemsKey($identifier, $instance));

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
        $data = $this->session->get($this->getConditionsKey($identifier, $instance));

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
        $this->session->put($this->getItemsKey($identifier, $instance), $items);
    }

    /**
     * Store cart conditions in storage
     *
     * @param  array<string, mixed>  $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->validateDataSize($conditions, 'conditions');
        $this->session->put($this->getConditionsKey($identifier, $instance), $conditions);
    }

    /**
     * Store both items and conditions for a cart instance
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
        $this->session->put($metadataKey, $value);
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

        foreach ($metadata as $key => $value) {
            $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
            $this->session->put($metadataKey, $value);
        }
    }

    /**
     * Retrieve cart metadata
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);

        return $this->session->get($metadataKey);
    }

    /**
     * Retrieve all cart metadata
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $metadataPrefix = "{$this->keyPrefix}.{$identifier}.{$instance}.metadata.";
        $allKeys = $this->session->all();
        $metadata = [];

        foreach ($allKeys as $key => $value) {
            if (str_starts_with((string) $key, $metadataPrefix)) {
                $metadataKey = mb_substr((string) $key, mb_strlen($metadataPrefix));
                $metadata[$metadataKey] = $value;
            }
        }

        return $metadata;
    }

    /**
     * Clear all metadata for a cart
     */
    public function clearMetadata(string $identifier, string $instance): void
    {
        $metadataPrefix = "{$this->keyPrefix}.{$identifier}.{$instance}.metadata.";
        $allKeys = $this->session->all();

        foreach (array_keys($allKeys) as $key) {
            if (str_starts_with((string) $key, $metadataPrefix)) {
                $this->session->forget($key);
            }
        }
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
     * Session storage doesn't support versioning, returns null
     */
    public function getVersion(string $identifier, string $instance): ?int
    {
        return null;
    }

    /**
     * Get cart ID (primary key) from storage
     * Session storage doesn't have IDs, returns null
     */
    public function getId(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Get cart creation timestamp (not supported by session storage)
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Get cart last updated timestamp (not supported by session storage)
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return null;
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
