<?php

declare(strict_types=1);

namespace AIArmada\Cart\Testing;

use AIArmada\Cart\Storage\StorageInterface;

/**
 * Simple in-memory storage implementation for testing purposes.
 *
 * This storage implementation keeps all data in memory arrays,
 * making it suitable for unit testing without external dependencies.
 */
class InMemoryStorage implements StorageInterface
{
    /** @var array<string, array<string, array<array-key, mixed>>> */
    private array $items = [];

    /** @var array<string, array<string, array<array-key, mixed>>> */
    private array $conditions = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $metadata = [];

    /** @var array<string, array<string, array<array-key, mixed>>> */
    private array $instances = [];

    /** @var array<string, array<string, int>> */
    private array $versions = [];

    /** @var array<string, array<string, string>> */
    private array $ids = [];

    public function has(string $identifier, string $instance): bool
    {
        return isset($this->items[$identifier][$instance]) ||
               isset($this->conditions[$identifier][$instance]);
    }

    public function getItems(string $identifier, string $instance): array
    {
        return $this->items[$identifier][$instance] ?? [];
    }

    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->items[$identifier][$instance] = $items;
        $this->incrementVersion($identifier, $instance);
    }

    public function getConditions(string $identifier, string $instance): array
    {
        return $this->conditions[$identifier][$instance] ?? [];
    }

    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->conditions[$identifier][$instance] = $conditions;
        $this->incrementVersion($identifier, $instance);
    }

    public function forget(string $identifier, string $instance): void
    {
        unset(
            $this->items[$identifier][$instance],
            $this->conditions[$identifier][$instance],
            $this->metadata[$identifier][$instance],
            $this->versions[$identifier][$instance],
            $this->ids[$identifier][$instance]
        );

        if (empty($this->items[$identifier])) {
            unset($this->items[$identifier]);
        }
        if (empty($this->conditions[$identifier])) {
            unset($this->conditions[$identifier]);
        }
        if (empty($this->metadata[$identifier])) {
            unset($this->metadata[$identifier]);
        }
        if (empty($this->versions[$identifier])) {
            unset($this->versions[$identifier]);
        }
        if (empty($this->ids[$identifier])) {
            unset($this->ids[$identifier]);
        }
    }

    public function forgetIdentifier(string $identifier): void
    {
        unset(
            $this->items[$identifier],
            $this->conditions[$identifier],
            $this->metadata[$identifier],
            $this->versions[$identifier],
            $this->ids[$identifier],
            $this->instances[$identifier]
        );
    }

    public function flush(): void
    {
        $this->items = [];
        $this->conditions = [];
        $this->metadata = [];
        $this->instances = [];
        $this->versions = [];
        $this->ids = [];
    }

    public function getInstances(string $identifier): array
    {
        return array_keys($this->instances[$identifier] ?? []);
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->metadata[$identifier][$instance][$key] = $value;
        $this->incrementVersion($identifier, $instance);
    }

    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        foreach ($metadata as $key => $value) {
            $this->metadata[$identifier][$instance][$key] = $value;
        }
        $this->incrementVersion($identifier, $instance);
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        return $this->metadata[$identifier][$instance][$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        return $this->metadata[$identifier][$instance] ?? [];
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        if (isset($this->metadata[$identifier][$instance])) {
            unset($this->metadata[$identifier][$instance]);
        }
        $this->incrementVersion($identifier, $instance);
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        return $this->versions[$identifier][$instance] ?? null;
    }

    public function getId(string $identifier, string $instance): ?string
    {
        if (! isset($this->ids[$identifier][$instance])) {
            $this->ids[$identifier][$instance] = uniqid('cart_', true);
        }

        return $this->ids[$identifier][$instance];
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        if (! isset($this->items[$oldIdentifier][$instance]) && ! isset($this->conditions[$oldIdentifier][$instance])) {
            return false;
        }

        if (isset($this->items[$oldIdentifier][$instance])) {
            $this->items[$newIdentifier][$instance] = $this->items[$oldIdentifier][$instance];
            unset($this->items[$oldIdentifier][$instance]);
        }

        if (isset($this->conditions[$oldIdentifier][$instance])) {
            $this->conditions[$newIdentifier][$instance] = $this->conditions[$oldIdentifier][$instance];
            unset($this->conditions[$oldIdentifier][$instance]);
        }

        if (isset($this->metadata[$oldIdentifier][$instance])) {
            $this->metadata[$newIdentifier][$instance] = $this->metadata[$oldIdentifier][$instance];
            unset($this->metadata[$oldIdentifier][$instance]);
        }

        if (isset($this->versions[$oldIdentifier][$instance])) {
            $this->versions[$newIdentifier][$instance] = $this->versions[$oldIdentifier][$instance];
            unset($this->versions[$oldIdentifier][$instance]);
        }

        if (isset($this->ids[$oldIdentifier][$instance])) {
            $this->ids[$newIdentifier][$instance] = $this->ids[$oldIdentifier][$instance];
            unset($this->ids[$oldIdentifier][$instance]);
        }

        return true;
    }

    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->items[$identifier][$instance] = $items;
        $this->conditions[$identifier][$instance] = $conditions;
        $this->incrementVersion($identifier, $instance);
    }

    /**
     * Get cart creation timestamp (not supported by in-memory storage)
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Get cart last updated timestamp (not supported by in-memory storage)
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    private function incrementVersion(string $identifier, string $instance): void
    {
        $current = $this->versions[$identifier][$instance] ?? 0;
        $this->versions[$identifier][$instance] = $current + 1;
    }
}
