<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Contracts\MerchantCatalog;
use AIArmada\Affiliates\Data\ProgramSnapshot;
use AIArmada\Affiliates\Models\AffiliateProgram;

/**
 * Merchant catalog seam: read-only program snapshots for mirrors.
 */
final class MerchantCatalogService implements MerchantCatalog
{
    public function __construct(
        private readonly ProgramCatalogService $catalog,
    ) {}

    public function snapshotById(string $programId): ?ProgramSnapshot
    {
        $program = AffiliateProgram::query()->whereKey($programId)->first();

        if (! $program instanceof AffiliateProgram) {
            return null;
        }

        $payload = $this->catalog->snapshot($program);

        return new ProgramSnapshot(
            programId: (string) $program->getKey(),
            currency: (string) ($payload['currency'] ?? config('affiliates.currency.default', 'MYR')),
            payload: $payload,
        );
    }

    public function mirrorableProgramIds(int $limit = 100): array
    {
        return AffiliateProgram::query()
            ->active()
            ->public()
            ->limit(max(1, $limit))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }
}
