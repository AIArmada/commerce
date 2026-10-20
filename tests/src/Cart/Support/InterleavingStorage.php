<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cart\Support;

use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\CartStorageSnapshot;
use AIArmada\Cart\Storage\StorageInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Storage decorator that deterministically interleaves a competing cart
 * mutation between the read and the write of the cart under test.
 *
 * Arm one hook before the operation under test. The hook runs exactly once,
 * after the decorated storage has produced the (now stale) read result that
 * the cart under test is about to base its write payload on:
 *
 * - 'getSnapshot' fires after the versioned snapshot read of a
 *   read/modify/write cycle, before the replacing write.
 * - 'putItems' fires after a guarded item replacement, before later merge
 *   steps consume the source revision the replacement was computed from.
 * - 'mutateMetadata' fires after the storage-owned metadata compute callback
 *   has produced its (now stale) value, before the guarded write.
 */
final class InterleavingStorage implements StorageInterface
{
    /** @var array{method: string, action: Closure}|null */
    private ?array $armed = null;

    public function __construct(private readonly StorageInterface $inner) {}

    public function getInner(): StorageInterface
    {
        return $this->inner;
    }

    /**
     * Run the competing mutation once, right after the next matching read.
     */
    public function interleaveAfter(string $method, Closure $action): void
    {
        $this->armed = ['method' => $method, 'action' => $action];
    }

    private function fire(string $method): void
    {
        if ($this->armed === null || $this->armed['method'] !== $method) {
            return;
        }

        $action = $this->armed['action'];
        $this->armed = null;
        $action();
    }

    public function withOwner(?Model $owner): static
    {
        return new self($this->inner->withOwner($owner));
    }

    public function getOwnerType(): ?string
    {
        return $this->inner->getOwnerType();
    }

    public function getOwnerId(): string | int | null
    {
        return $this->inner->getOwnerId();
    }

    public function has(string $identifier, string $instance): bool
    {
        return $this->inner->has($identifier, $instance);
    }

    public function forget(string $identifier, string $instance): void
    {
        $this->inner->forget($identifier, $instance);
    }

    public function getItems(string $identifier, string $instance): array
    {
        $items = $this->inner->getItems($identifier, $instance);
        $this->fire('getItems');

        return $items;
    }

    public function getSnapshot(string $identifier, string $instance): ?CartStorageSnapshot
    {
        $snapshot = $this->inner->getSnapshot($identifier, $instance);
        $this->fire('getSnapshot');

        return $snapshot;
    }

    public function getConditions(string $identifier, string $instance): array
    {
        return $this->inner->getConditions($identifier, $instance);
    }

    public function putItems(string $identifier, string $instance, array $items, CartRevision $expectedRevision): CartRevision
    {
        $revision = $this->inner->putItems($identifier, $instance, $items, $expectedRevision);
        $this->fire('putItems');

        return $revision;
    }

    public function putConditions(string $identifier, string $instance, array $conditions, CartRevision $expectedRevision): CartRevision
    {
        return $this->inner->putConditions($identifier, $instance, $conditions, $expectedRevision);
    }

    public function putBoth(string $identifier, string $instance, array $items, array $conditions, CartRevision $expectedRevision): CartRevision
    {
        return $this->inner->putBoth($identifier, $instance, $items, $conditions, $expectedRevision);
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $value = $this->inner->getMetadata($identifier, $instance, $key);
        $this->fire('getMetadata');

        return $value;
    }

    public function getAllMetadata(string $identifier, string $instance): array
    {
        return $this->inner->getAllMetadata($identifier, $instance);
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->inner->putMetadata($identifier, $instance, $key, $value);
    }

    public function mutateMetadata(string $identifier, string $instance, string $key, Closure $compute): mixed
    {
        return $this->inner->mutateMetadata($identifier, $instance, $key, function (mixed $current) use ($compute): mixed {
            $computed = $compute($current);

            $this->fire('mutateMetadata');

            return $computed;
        });
    }

    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        $this->inner->putMetadataBatch($identifier, $instance, $metadata);
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->inner->clearMetadata($identifier, $instance);
    }

    public function clearAll(string $identifier, string $instance): void
    {
        $this->inner->clearAll($identifier, $instance);
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        return $this->inner->getVersion($identifier, $instance);
    }

    public function getId(string $identifier, string $instance): ?string
    {
        return $this->inner->getId($identifier, $instance);
    }

    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return $this->inner->getCreatedAt($identifier, $instance);
    }

    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return $this->inner->getUpdatedAt($identifier, $instance);
    }

    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        return $this->inner->getExpiresAt($identifier, $instance);
    }

    public function isExpired(string $identifier, string $instance): bool
    {
        return $this->inner->isExpired($identifier, $instance);
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        return $this->inner->swapIdentifier($oldIdentifier, $newIdentifier, $instance);
    }

    public function consumeForMerge(string $identifier, string $instance, CartRevision $expectedRevision, string $mergedIntoId): CartRevision
    {
        return $this->inner->consumeForMerge($identifier, $instance, $expectedRevision, $mergedIntoId);
    }

    public function backingKey(): string
    {
        return $this->inner->backingKey();
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
        $this->fire('commitMergeTransfer');

        $unwrappedSource = $sourceStorage instanceof self ? $sourceStorage->getInner() : $sourceStorage;

        return $this->inner->commitMergeTransfer(
            $unwrappedSource,
            $sourceIdentifier,
            $targetIdentifier,
            $instance,
            $items,
            $conditions,
            $metadata,
            $sourceRevision,
            $targetRevision,
        );
    }

    public function forgetIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool
    {
        return $this->inner->forgetIfUnchanged($identifier, $instance, $expectedRevision);
    }

    public function clearAllIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool
    {
        return $this->inner->clearAllIfUnchanged($identifier, $instance, $expectedRevision);
    }

    public function flush(): void
    {
        $this->inner->flush();
    }

    public function getInstances(string $identifier): array
    {
        return $this->inner->getInstances($identifier);
    }

    public function forgetIdentifier(string $identifier): void
    {
        $this->inner->forgetIdentifier($identifier);
    }
}
