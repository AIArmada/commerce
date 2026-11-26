<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSummary;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutExportRow;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadPayoutConversionIntegrity implements PayoutConversionIntegrity
{
    public function reportTotals(array $payoutOwners, int $connectionIdentity): array
    {
        $this->assertConnection($connectionIdentity);
        $result = [];
        $ownerEnabled = (bool) config('affiliates.owner.enabled', false);

        foreach (array_chunk(array_keys($payoutOwners), 500) as $ids) {
            $rows = AffiliateConversion::query()->forOwner(OwnerContext::CURRENT, true)
                ->whereIn('affiliate_payout_id', $ids)
                ->select(['affiliate_payout_id', 'owner_type', 'owner_id', 'commission_currency'])
                ->selectRaw('SUM(commission_minor) AS total_minor, COUNT(*) AS conversion_count')
                ->groupBy('affiliate_payout_id', 'owner_type', 'owner_id', 'commission_currency')
                ->toBase()->get();

            foreach ($rows as $row) {
                $id = (string) $row->affiliate_payout_id;
                $owner = $payoutOwners[$id];

                if ($ownerEnabled && ($row->owner_type !== $owner['owner_type'] || $row->owner_id !== $owner['owner_id'])) {
                    continue;
                }

                $result[$id] ??= ['total_minor' => 0, 'currencies' => [], 'count' => 0];
                $result[$id]['total_minor'] += (int) $row->total_minor;
                $result[$id]['count'] += (int) $row->conversion_count;
                $currency = mb_strtoupper((string) $row->commission_currency);

                if (! in_array($currency, $result[$id]['currencies'], true)) {
                    $result[$id]['currencies'][] = $currency;
                }
            }
        }

        foreach ($result as &$totals) {
            sort($totals['currencies']);
        }

        return $result;
    }

    public function exportRows(string $payoutId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): iterable
    {
        $this->assertConnection($connectionIdentity);

        if (! Str::isUuid($payoutId)) {
            return;
        }

        $query = AffiliateConversion::query()->forOwner(OwnerContext::CURRENT, true)
            ->where('affiliate_payout_id', $payoutId);

        if ((bool) config('affiliates.owner.enabled', false)) {
            $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        }

        foreach ($query->select(['id', 'affiliate_code', 'external_reference', 'commission_minor', 'commission_currency', 'status', 'created_at'])->lazyById(500) as $conversion) {
            yield new PayoutExportRow(
                (string) $conversion->affiliate_code,
                $conversion->external_reference,
                (int) $conversion->commission_minor,
                (string) $conversion->commission_currency,
                $conversion->status->getValue(),
                $conversion->created_at?->format('Y-m-d H:i:s'),
            );
        }
    }

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
        $this->assertConnection($connectionIdentity);

        return AffiliateConversion::query()->withoutOwnerScope()->where('affiliate_payout_id', $payoutId);
    }

    private function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Payout conversion integrity requires a shared database connection.');
        }
    }
}
