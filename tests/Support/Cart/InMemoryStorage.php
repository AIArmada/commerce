<?php

declare(strict_types=1);

namespace Tests\Support\Cart;

use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\CartStorageSnapshot;
use AIArmada\Cart\Storage\StorageInterface;
use ArrayObject;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * In-memory storage implementation for testing purposes.
 *
 * This class was moved from packages/cart/src/Testing to tests/Support
 * as part of the cart package simplification.
 *
 * It enforces the same revision contract as the database storage so tests
 * exercise identical atomicity semantics without a database.
 */
final class InMemoryStorage implements StorageInterface
{
    /**
     * @var array<string, array{
     *     id: string,
     *     items: array<string, mixed>,
     *     conditions: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     version: int,
     *     created_at: string,
     *     updated_at: string,
     *     expires_at: string|null,
     *     expired_at: string|null,
     *     merged_into_id: string|null
     * }>
     */
    private ArrayObject $carts;

    private ?string $ownerType = null;

    private string | int | null $ownerId = null;

    /**
     * Maximum number of times a metadata merge re-reads fresh content before
     * surfacing the conflict to the caller.
     */
    private const MAX_METADATA_ATTEMPTS = 5;

    public function __construct(?ArrayObject $carts = null)
    {
        $this->carts = $carts ?? new ArrayObject;
    }

    public function has(string $identifier, string $instance): bool
    {
        return isset($this->carts[$this->key($identifier, $instance)]);
    }

    public function forget(string $identifier, string $instance): void
    {
        unset($this->carts[$this->key($identifier, $instance)]);
    }

    public function flush(): void
    {
        $this->carts->exchangeArray([]);
    }

    /**
     * @return array<string>
     */
    public function getInstances(string $identifier): array
    {
        $instances = [];
        foreach (array_keys($this->carts) as $key) {
            if (str_starts_with((string) $key, $identifier . ':')) {
                $instances[] = mb_substr((string) $key, mb_strlen($identifier) + 1);
            }
        }

        return $instances;
    }

    public function forgetIdentifier(string $identifier): void
    {
        foreach (array_keys($this->carts) as $key) {
            if (str_starts_with((string) $key, $identifier . ':')) {
                unset($this->carts[$key]);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getItems(string $identifier, string $instance): array
    {
        return $this->getCart($identifier, $instance)['items'] ?? [];
    }

    public function getSnapshot(string $identifier, string $instance): ?CartStorageSnapshot
    {
        $cart = $this->getCart($identifier, $instance);

        if ($cart === []) {
            return null;
        }

        return new CartStorageSnapshot(
            id: $cart['id'],
            version: $cart['version'],
            items: $cart['items'],
            conditions: $cart['conditions'],
            metadata: $cart['metadata'],
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     */
    public function putItems(string $identifier, string $instance, array $items, CartRevision $expectedRevision): CartRevision
    {
        return $this->replace($identifier, $instance, ['items' => $items], $expectedRevision);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getConditions(string $identifier, string $instance): array
    {
        return $this->getCart($identifier, $instance)['conditions'] ?? [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions, CartRevision $expectedRevision): CartRevision
    {
        return $this->replace($identifier, $instance, ['conditions' => $conditions], $expectedRevision);
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions, CartRevision $expectedRevision): CartRevision
    {
        return $this->replace($identifier, $instance, [
            'items' => $items,
            'conditions' => $conditions,
        ], $expectedRevision);
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->mutateMetadata($identifier, $instance, $key, static fn (): mixed => $value);
    }

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

            if ($snapshot !== null && $metadata === $snapshot->metadata) {
                return $newValue;
            }

            if ($snapshot === null && $metadata === []) {
                return $newValue;
            }

            try {
                $this->replace($identifier, $instance, [
                    'metadata' => $metadata,
                ], $snapshot?->revision() ?? CartRevision::none());

                return $newValue;
            } catch (CartConflictException $exception) {
                if ($attempt >= self::MAX_METADATA_ATTEMPTS) {
                    throw $exception;
                }

                usleep(25000 * $attempt);
            }
        }
    }

    /**
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

            $merged = array_filter(
                array_merge($snapshot?->metadata ?? [], $metadata),
                fn ($value) => $value !== null
            );

            if ($snapshot !== null && $merged === $snapshot->metadata) {
                return;
            }

            if ($snapshot === null && $merged === []) {
                return;
            }

            try {
                $this->replace($identifier, $instance, [
                    'metadata' => $merged,
                ], $snapshot?->revision() ?? CartRevision::none());

                return;
            } catch (CartConflictException $exception) {
                if ($attempt >= self::MAX_METADATA_ATTEMPTS) {
                    throw $exception;
                }

                usleep(25000 * $attempt);
            }
        }
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        return $this->getCart($identifier, $instance)['metadata'][$key] ?? null;
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->upsertWrite($identifier, $instance, ['metadata' => []]);
    }

    public function clearAll(string $identifier, string $instance): void
    {
        $this->upsertWrite($identifier, $instance, [
            'items' => [],
            'conditions' => [],
            'metadata' => [],
        ]);
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        $oldKey = $this->key($oldIdentifier, $instance);
        $newKey = $this->key($newIdentifier, $instance);

        if (! isset($this->carts[$oldKey])) {
            return false;
        }

        if ($oldKey === $newKey) {
            return true;
        }

        if (isset($this->carts[$newKey])) {
            return false;
        }

        $cart = $this->carts[$oldKey];
        $cart['version']++;
        $cart['updated_at'] = CarbonImmutable::now()->toIso8601String();

        $this->carts[$newKey] = $cart;
        unset($this->carts[$oldKey]);

        return true;
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        return $this->getCart($identifier, $instance)['version'] ?? null;
    }

    public function getId(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        return $this->getCart($identifier, $instance)['metadata'] ?? [];
    }

    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['created_at'] ?? null;
    }

    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['updated_at'] ?? null;
    }

    public function withOwner(?Model $owner): static
    {
        $clone = clone $this;
        $clone->ownerType = $owner?->getMorphClass();
        $clone->ownerId = $owner?->getKey();

        return $clone;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function getOwnerId(): string | int | null
    {
        return $this->ownerId;
    }

    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['expires_at'] ?? null;
    }

    public function isExpired(string $identifier, string $instance): bool
    {
        $cart = $this->getCart($identifier, $instance);

        return ($cart['expired_at'] ?? null) !== null;
    }

    /**
     * Consume a source cart for a merge, guarded by the exact revision it was
     * read at.
     *
     * @throws CartConflictException When the source revision no longer matches or was already consumed
     */
    public function consumeForMerge(string $identifier, string $instance, CartRevision $expectedRevision, string $mergedIntoId): CartRevision
    {
        if ($expectedRevision->isCreation()) {
            throw new InvalidArgumentException('A merge consumption requires an existing source revision.');
        }

        $key = $this->key($identifier, $instance);
        $current = $this->carts[$key] ?? null;

        if (
            $current === null
            || $current['id'] !== $expectedRevision->cartId
            || $current['version'] !== $expectedRevision->version
            || $current['merged_into_id'] !== null
        ) {
            throw new CartConflictException(
                'Cart was modified by another request during merge consumption',
                $expectedRevision->version ?? 0,
                $current['version'] ?? 0
            );
        }

        $now = CarbonImmutable::now()->toIso8601String();

        $this->carts[$key] = array_merge($current, [
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'merged_into_id' => $mergedIntoId,
            'expired_at' => $now,
            'version' => $current['version'] + 1,
            'updated_at' => $now,
        ]);

        return CartRevision::of($current['id'], $current['version'] + 1);
    }

    /**
     * Remove a cart only while the expected revision is still current.
     *
     * @throws InvalidArgumentException When the expected revision is a creation
     */
    public function forgetIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool
    {
        if ($expectedRevision->isCreation()) {
            throw new InvalidArgumentException('A guarded cart removal requires an existing revision.');
        }

        $key = $this->key($identifier, $instance);
        $current = $this->carts[$key] ?? null;

        if ($current === null || $current['id'] !== $expectedRevision->cartId || $current['version'] !== $expectedRevision->version) {
            return false;
        }

        unset($this->carts[$key]);

        return true;
    }

    /**
     * Clear a cart only while the expected revision is still current.
     *
     * @throws InvalidArgumentException When the expected revision is a creation
     */
    public function clearAllIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool
    {
        if ($expectedRevision->isCreation()) {
            throw new InvalidArgumentException('A guarded cart clear requires an existing revision.');
        }

        $key = $this->key($identifier, $instance);
        $current = $this->carts[$key] ?? null;

        if ($current === null || $current['id'] !== $expectedRevision->cartId || $current['version'] !== $expectedRevision->version) {
            return false;
        }

        $this->carts[$key] = array_merge($current, [
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'version' => $current['version'] + 1,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        return true;
    }

    public function backingKey(): string
    {
        return 'inmemory:' . spl_object_id($this->carts);
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

        $targetKey = $this->key($targetIdentifier, $instance);
        $sourceKey = $sourceStorage->key($sourceIdentifier, $instance);

        $currentTarget = $this->carts[$targetKey] ?? null;
        $currentSource = $this->carts[$sourceKey] ?? null;

        if ($targetRevision->isCreation()) {
            if ($currentTarget !== null) {
                throw new CartConflictException(
                    'Cart was modified by another request during merge transfer',
                    0,
                    $currentTarget['version'] ?? 0
                );
            }
        } else {
            if (
                $currentTarget === null
                || $currentTarget['id'] !== $targetRevision->cartId
                || $currentTarget['version'] !== $targetRevision->version
            ) {
                throw new CartConflictException(
                    'Cart was modified by another request during merge transfer',
                    $targetRevision->version ?? 0,
                    $currentTarget['version'] ?? 0
                );
            }
        }

        if ($sourceRevision->isCreation() || $currentSource === null) {
            throw new CartConflictException(
                'Cart was modified by another request during merge transfer',
                $sourceRevision->version ?? 0,
                $currentSource['version'] ?? 0
            );
        }

        if (
            $currentSource['id'] !== $sourceRevision->cartId
            || $currentSource['version'] !== $sourceRevision->version
            || $currentSource['merged_into_id'] !== null
        ) {
            throw new CartConflictException(
                'Cart was modified by another request during merge transfer',
                $sourceRevision->version ?? 0,
                $currentSource['version'] ?? 0
            );
        }

        $oldTarget = $currentTarget;
        $now = CarbonImmutable::now()->toIso8601String();

        $targetCartId = null;

        if ($targetRevision->isCreation()) {
            $targetCartId = (string) Str::uuid();
            $targetRow = array_merge([
                'items' => [],
                'conditions' => [],
                'metadata' => [],
            ], [
                'items' => $items,
                'conditions' => $conditions,
                'metadata' => array_merge($currentTarget['metadata'] ?? [], $metadata),
            ], [
                'id' => $targetCartId,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => null,
                'expired_at' => null,
                'merged_into_id' => null,
            ]);
            $this->carts[$targetKey] = $targetRow;
            $newTargetVersion = 1;
        } else {
            $targetCartId = (string) $currentTarget['id'];
            $mergedMetadata = array_merge($currentTarget['metadata'] ?? [], $metadata);
            $this->carts[$targetKey] = array_merge($currentTarget, [
                'items' => $items,
                'conditions' => $conditions,
                'metadata' => $mergedMetadata,
                'version' => $currentTarget['version'] + 1,
                'updated_at' => $now,
            ]);
            $newTargetVersion = $currentTarget['version'] + 1;
        }

        try {
            $freshSource = $this->carts[$sourceKey] ?? null;

            if (
                $freshSource === null
                || $freshSource['id'] !== $sourceRevision->cartId
                || $freshSource['version'] !== $sourceRevision->version
                || $freshSource['merged_into_id'] !== null
            ) {
                throw new CartConflictException(
                    'Cart was modified by another request during merge transfer',
                    $sourceRevision->version ?? 0,
                    $freshSource['version'] ?? 0
                );
            }

            $this->carts[$sourceKey] = array_merge($freshSource, [
                'items' => [],
                'conditions' => [],
                'metadata' => [],
                'merged_into_id' => $targetCartId,
                'expired_at' => $now,
                'version' => $freshSource['version'] + 1,
                'updated_at' => $now,
            ]);

            return CartRevision::of($freshSource['id'], $freshSource['version'] + 1);
        } catch (CartConflictException $exception) {
            $targetNow = $this->carts[$targetKey] ?? null;

            if (
                $targetNow !== null
                && $targetNow['id'] === $targetCartId
                && $targetNow['version'] === $newTargetVersion
            ) {
                if ($oldTarget === null) {
                    unset($this->carts[$targetKey]);
                } else {
                    $this->carts[$targetKey] = $oldTarget;
                }
            }

            throw $exception;
        }
    }

    private function key(string $identifier, string $instance): string
    {
        return $identifier . ':' . $instance;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCart(string $identifier, string $instance): array
    {
        return $this->carts[$this->key($identifier, $instance)] ?? [];
    }

    /**
     * Atomically replace a cart row at the expected revision.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CartConflictException When the expected revision no longer matches
     */
    private function replace(string $identifier, string $instance, array $data, CartRevision $expectedRevision): CartRevision
    {
        $key = $this->key($identifier, $instance);
        $current = $this->carts[$key] ?? null;

        if ($expectedRevision->isCreation()) {
            if ($current !== null) {
                throw new CartConflictException(
                    'Cart was modified by another request during replace',
                    0,
                    $current['version']
                );
            }

            $now = CarbonImmutable::now()->toIso8601String();

            $cart = array_merge([
                'items' => [],
                'conditions' => [],
                'metadata' => [],
            ], $data, [
                'id' => Str::uuid()->toString(),
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => null,
                'expired_at' => null,
                'merged_into_id' => null,
            ]);

            $this->carts[$key] = $cart;

            return CartRevision::of($cart['id'], 1);
        }

        if ($current === null || $current['id'] !== $expectedRevision->cartId || $current['version'] !== $expectedRevision->version) {
            throw new CartConflictException(
                'Cart was modified by another request during replace',
                $expectedRevision->version ?? 0,
                $current['version'] ?? 0
            );
        }

        $this->carts[$key] = array_merge($current, $data, [
            'version' => $current['version'] + 1,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        return CartRevision::of($current['id'], $current['version'] + 1);
    }

    /**
     * Blind upsert write keyed only by the identifier/instance pair.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertWrite(string $identifier, string $instance, array $data): void
    {
        $key = $this->key($identifier, $instance);

        if (isset($this->carts[$key])) {
            $this->carts[$key] = array_merge($this->carts[$key], $data, [
                'version' => $this->carts[$key]['version'] + 1,
                'updated_at' => CarbonImmutable::now()->toIso8601String(),
            ]);

            return;
        }

        $now = CarbonImmutable::now()->toIso8601String();

        $this->carts[$key] = array_merge([
            'items' => [],
            'conditions' => [],
            'metadata' => [],
        ], $data, [
            'id' => Str::uuid()->toString(),
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => null,
            'expired_at' => null,
            'merged_into_id' => null,
        ]);
    }
}
