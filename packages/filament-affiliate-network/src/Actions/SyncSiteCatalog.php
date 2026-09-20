<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferImportService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Notifications\Notification;

/**
 * Sync every available program catalog for a site and report the counts.
 *
 * Runs inside the site's own owner context so locally-read programs and
 * the sync stamp stay under owner scoping.
 */
final class SyncSiteCatalog
{
    /**
     * @return array{programs: int, created: int, updated: int, skipped: int, locked: int, failed: int}
     */
    public function handle(AffiliateSite $record): array
    {
        $owner = OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);

        $result = OwnerContext::withOwner($owner, function () use ($record): array {
            $site = AffiliateSite::query()->whereKey($record->getKey())->firstOrFail();

            return app(OfferImportService::class)->syncAll($site);
        });

        Notification::make()
            ->title('Catalog sync complete')
            ->body(sprintf(
                '%d program(s): %d created, %d updated, %d skipped, %d failed.',
                $result['programs'],
                $result['created'],
                $result['updated'],
                $result['skipped'],
                $result['failed'],
            ))
            ->success()
            ->send();

        return $result;
    }
}
