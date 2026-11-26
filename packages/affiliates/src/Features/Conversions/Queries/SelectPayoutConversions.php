<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutAllocation;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSelection;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutSelectionRecord;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class SelectPayoutConversions implements PayoutConversionSelection
{
    public function manual(array $conversionIds, int $connectionIdentity): PayoutSelectionRecord
    {
        $this->assertConnection($connectionIdentity, true);

        if ($conversionIds === [] || count($conversionIds) !== count(array_unique($conversionIds))) {
            throw new InvalidArgumentException('A payout requires unique conversion IDs.');
        }

        foreach ($conversionIds as $id) {
            if (! Str::isUuid($id)) {
                throw new InvalidArgumentException('All conversions must be approved and not already linked to a payout.');
            }
        }

        $rows = AffiliateConversion::query()->forOwner()
            ->whereIn('id', $conversionIds)
            ->where('commission_minor', '>', 0)
            ->where('status', ApprovedConversion::value())
            ->whereNull('affiliate_payout_id')
            ->select(['affiliate_id', 'owner_type', 'owner_id', 'commission_currency', 'commission_minor', 'conversion_type'])
            ->orderBy('id')
            ->lockForUpdate()
            ->toBase()
            ->get();

        if ($rows->count() !== count($conversionIds)) {
            throw new InvalidArgumentException('All conversions must be approved and not already linked to a payout.');
        }

        $first = $rows->first();

        if ($first === null) {
            throw new LogicException('Payout conversion selection returned no records.');
        }

        $affiliateId = (string) $first->affiliate_id;
        $ownerType = $first->owner_type === null ? null : (string) $first->owner_type;
        $ownerId = $first->owner_id === null ? null : (string) $first->owner_id;
        $currency = mb_strtoupper((string) $first->commission_currency);
        $total = 0;
        $onlyRewardAwards = true;

        foreach ($rows as $row) {
            if ((string) $row->affiliate_id !== $affiliateId) {
                throw new InvalidArgumentException('A payout operation may contain conversions for only one affiliate.');
            }

            if ($row->owner_type !== $first->owner_type || $row->owner_id !== $first->owner_id) {
                throw new InvalidArgumentException('A payout operation may contain conversions for only one owner.');
            }

            if (mb_strtoupper((string) $row->commission_currency) !== $currency) {
                throw new InvalidArgumentException('A payout operation may contain conversions in only one currency.');
            }

            $total += (int) $row->commission_minor;
            $onlyRewardAwards = $onlyRewardAwards && $row->conversion_type === 'reward_award';
        }

        return new PayoutSelectionRecord($affiliateId, $ownerType, $ownerId, $currency, $total, $rows->count(), $onlyRewardAwards);
    }

    public function scheduled(string $affiliateId, int $availableMinor, string $currency, bool $lockForUpdate, int $connectionIdentity): PayoutAllocation
    {
        $this->assertConnection($connectionIdentity, $lockForUpdate);

        if ($availableMinor <= 0) {
            return new PayoutAllocation([], 0);
        }

        $query = AffiliateConversion::query()->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->where('status', ApprovedConversion::value())
            ->whereNull('affiliate_payout_id')
            ->whereRaw('UPPER(commission_currency) = ?', [mb_strtoupper($currency)])
            ->where('commission_minor', '>', 0)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->select(['id', 'commission_minor']);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $conversionIds = [];
        $amountMinor = 0;

        foreach ($query->toBase()->lazy(1000) as $conversion) {
            $commissionMinor = (int) $conversion->commission_minor;

            if ($commissionMinor > $availableMinor - $amountMinor) {
                break;
            }

            $conversionIds[] = (string) $conversion->id;
            $amountMinor += $commissionMinor;
        }

        return new PayoutAllocation($conversionIds, $amountMinor);
    }

    private function assertConnection(int $connectionIdentity, bool $requiresTransaction): void
    {
        $connection = (new AffiliateConversion)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || ($requiresTransaction && $connection->transactionLevel() < 1)) {
            throw new LogicException('Payout conversion selection requires the shared accounting connection.');
        }
    }
}
