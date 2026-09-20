<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Actions\Payouts\ClaimScheduledPayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Throwable;

/**
 * Run the scheduled payout sweep inside the current owner context.
 *
 * Mirrors affiliates:process-payouts without the cross-owner batch runner:
 * the UI only ever sweeps the current owner's balances. Dry runs use the
 * same eligibility snapshot as real claims, so the preview is exact.
 */
final class RunScheduledPayoutSweep
{
    /**
     * @return array{processed:int,skipped:int,errors:int}
     */
    public function handle(bool $dryRun = true, ?string $affiliateId = null, int $minimum = 0): array
    {
        $affiliateId = $affiliateId !== null && mb_trim($affiliateId) !== '' ? mb_trim($affiliateId) : null;

        if ($affiliateId !== null && (bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
        }

        $claim = app(ClaimScheduledPayout::class);
        $summary = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        AffiliateBalance::query()
            ->where('available_minor', $minimum > 0 ? '>=' : '>', $minimum > 0 ? $minimum : 0)
            ->whereHas('affiliate', static function ($query): void {
                $query->where('status', AffiliateStatus::normalize(Active::class));
            })
            ->when($affiliateId !== null, fn ($query) => $query->where('affiliate_id', $affiliateId))
            ->select('id', 'affiliate_id', 'currency')
            ->orderBy('id')
            ->chunkById(100, function ($balances) use ($claim, $minimum, $dryRun, &$summary): void {
                foreach ($balances as $balance) {
                    $id = (string) $balance->affiliate_id;
                    $currency = (string) $balance->currency;

                    if ($dryRun) {
                        $claim->isEligibleSnapshot($id, $minimum, $currency)
                            ? ++$summary['processed']
                            : ++$summary['skipped'];

                        continue;
                    }

                    try {
                        $claim->handle($id, $minimum, $currency) === null
                            ? ++$summary['skipped']
                            : ++$summary['processed'];
                    } catch (Throwable) {
                        $summary['errors']++;
                    }
                }
            }, 'id');

        return $summary;
    }
}
