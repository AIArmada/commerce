<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\ProgramSnapshot;

/**
 * Merchant catalog seam.
 *
 * Read-only program snapshots for mirrors and storefronts. Snapshot reads
 * never record attribution, commission, or payout activity.
 */
interface MerchantCatalog
{
    public function snapshotById(string $programId): ?ProgramSnapshot;

    /**
     * @return array<int, string> Active public program ids for mirroring.
     */
    public function mirrorableProgramIds(int $limit = 100): array;
}
