<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Api;

use AIArmada\Cart\Data\CartSnapshotData;

interface SnapshotConditionLookup
{
    public function requireOwnedSnapshot(string $snapshotId): CartSnapshotData;

    public function requireOwnedCondition(string $snapshotConditionId): SnapshotConditionMatch;

    /** @return iterable<SnapshotConditionMatch> */
    public function matchingStoredCondition(string $conditionId, bool $allOwners = false): iterable;

    /** @return iterable<SnapshotConditionMatch> */
    public function matchingNamedCondition(string $name): iterable;
}
