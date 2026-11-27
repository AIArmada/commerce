<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Api;

use AIArmada\Cart\Data\CartSnapshotData;

interface SnapshotConditionLookup
{
    public function requireOwnedSnapshot(string $snapshotId): CartSnapshotData;

    public function requireOwnedCondition(string $snapshotConditionId): SnapshotConditionMatch;

    /** @return list<SnapshotConditionMatch> */
    public function matchingStoredCondition(string $conditionId, bool $allOwners = false): array;

    /** @return list<SnapshotConditionMatch> */
    public function matchingNamedCondition(string $name): array;
}
