<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\LiveCart\Api;

use Carbon\CarbonInterface;

interface AbandonedCartCleanup
{
    /** @return list<array<string, string|int|null>> */
    public function owners(string $table): array;

    public function count(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode,
    ): int;

    public function execute(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        int $batchSize,
        ?string $ownerType,
        string | int | null $ownerId,
        bool $deleteMode,
    ): int;
}
