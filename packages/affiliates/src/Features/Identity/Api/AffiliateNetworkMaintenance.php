<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateNetworkMaintenance
{
    /**
     * @param list<string> $ids
     * @return array<string, AffiliateNetworkMember>
     */
    public function lockMembers(array $ids, int $connectionIdentity): array;

    public function synchronizeParent(string $affiliateId, ?string $sponsorId, int $connectionIdentity): void;

    /** @param list<string> $ids */
    public function clearNetwork(array $ids, int $connectionIdentity): void;

    /** @param array<string, array{direct: int, total: int, depth: int}> $counts */
    public function synchronizeCounts(array $counts, int $connectionIdentity): void;
}
