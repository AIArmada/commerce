<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Network;

use AIArmada\AffiliateNetwork\Exceptions\OfferNotFoundException;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderInterface;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\ProgramCatalogService;
use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Local reader for shared-DB installs. Calls affiliates' catalog service
 * directly — no HTTP. The reader is strictly read-only:
 * ProgramCatalogService::snapshot() reads catalog data and never records
 * attribution, commission, or payout activity.
 */
final class LocalProgramReader implements CatalogReaderInterface
{
    public function snapshot(AffiliateSite $site, string $programId): array
    {
        // A site mirrors its own owner's programs — never whatever ambient
        // context the caller happens to run in.
        return OwnerContext::withOwner($site->owner, function () use ($programId): array {
            $program = AffiliateProgram::query()->whereKey($programId)->first();

            if (! $program) {
                throw new OfferNotFoundException('Program not found for catalog snapshot.');
            }

            return app(ProgramCatalogService::class)->snapshot($program);
        });
    }

    public function programIds(AffiliateSite $site): array
    {
        $maxPrograms = max(1, (int) config('affiliate-network.sync.max_programs', 100));

        return OwnerContext::withOwner($site->owner, fn (): array => AffiliateProgram::query()
            ->active()
            ->public()
            ->limit($maxPrograms)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all());
    }
}
