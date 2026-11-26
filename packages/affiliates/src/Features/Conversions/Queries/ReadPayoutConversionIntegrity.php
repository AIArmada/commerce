<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSummary;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class ReadPayoutConversionIntegrity implements PayoutConversionIntegrity
{
    public function hasConversions(string $payoutId, int $connectionIdentity): bool
    {
        return $this->linked($payoutId, $connectionIdentity)->exists();
    }

    public function currencies(string $payoutId, int $connectionIdentity): array
    {
        return $this->linked($payoutId, $connectionIdentity)
            ->distinct()
            ->pluck('commission_currency')
            ->map(static fn (mixed $currency): string => mb_strtoupper((string) $currency))
            ->unique()
            ->values()
            ->all();
    }

    public function affiliateIds(string $payoutId, int $connectionIdentity, ?string $exceptConversionId = null): array
    {
        $query = $this->linked($payoutId, $connectionIdentity);

        if ($exceptConversionId !== null) {
            $query->whereKeyNot($exceptConversionId);
        }

        return $query->distinct()->pluck('affiliate_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function summary(string $payoutId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): PayoutConversionSummary
    {
        $groups = $this->linked($payoutId, $connectionIdentity)
            ->select(['affiliate_id', 'owner_type', 'owner_id'])
            ->selectRaw('COUNT(*) AS conversion_count, COALESCE(SUM(commission_minor), 0) AS total_minor')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS approved_count', [ApprovedConversion::value()])
            ->selectRaw('COUNT(CASE WHEN conversion_type = ? THEN 1 END) AS reward_award_count', ['reward_award'])
            ->groupBy('affiliate_id', 'owner_type', 'owner_id')
            ->toBase()
            ->get();

        $affiliateIds = [];
        $count = $totalMinor = $approvedCount = $rewardAwardCount = $foreignOwnerCount = 0;
        $ownerEnabled = (bool) config('affiliates.owner.enabled', false);

        foreach ($groups as $group) {
            $affiliateIds[] = (string) $group->affiliate_id;
            $groupCount = (int) $group->conversion_count;
            $count += $groupCount;
            $totalMinor += (int) $group->total_minor;
            $approvedCount += (int) $group->approved_count;
            $rewardAwardCount += (int) $group->reward_award_count;

            if ($ownerEnabled && ($group->owner_type !== $ownerType || $group->owner_id !== $ownerId)) {
                $foreignOwnerCount += $groupCount;
            }
        }

        return new PayoutConversionSummary(
            array_values(array_unique($affiliateIds)),
            $count,
            $totalMinor,
            $approvedCount,
            $rewardAwardCount,
            $foreignOwnerCount,
        );
    }

    /** @return Builder<AffiliateConversion> */
    private function linked(string $payoutId, int $connectionIdentity): Builder
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Payout conversion integrity requires a shared database connection.');
        }

        return AffiliateConversion::query()->withoutOwnerScope()->where('affiliate_payout_id', $payoutId);
    }
}
