<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Queries;

use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Features\Benefits\Api\ConsumedRewardBenefit;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitRecoveryApi;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use InvalidArgumentException;

final readonly class RewardBenefitRecoveryQuery implements RewardBenefitRecoveryApi
{
    public function consumedGrantsForReversal(string $awardId): array
    {
        $grants = RewardBenefitGrant::query()
            ->where('award_id', $awardId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($grants as $grant) {
            if (in_array($grant->status, [RewardBenefitGrantStatus::Reserved, RewardBenefitGrantStatus::Issued], true)) {
                throw new InvalidArgumentException(
                    'Benefit reversal refuses while grant [' . $grant->grant_key . '] is ['
                    . $grant->status->value . ']; cancel, revoke-unused, or confirm use first.'
                );
            }
        }

        $consumed = $grants->filter(static fn (RewardBenefitGrant $grant): bool => $grant->status === RewardBenefitGrantStatus::Consumed);
        $consumed->load('item');

        $snapshots = [];

        foreach ($consumed as $grant) {
            $item = $grant->item;

            if (! $item instanceof RewardBenefitItem) {
                throw new InvalidArgumentException('Consumed benefit grant is missing its frozen catalog item.');
            }

            $snapshots[] = new ConsumedRewardBenefit((string) $grant->getKey(), $item->benefit_key);
        }

        return $snapshots;
    }

    public function assertGrantBelongsToAward(string $grantId, string $awardId): void
    {
        $grant = RewardBenefitGrant::query()
            ->whereKey($grantId)
            ->where('award_id', $awardId)
            ->lockForUpdate()
            ->first();

        if (! $grant instanceof RewardBenefitGrant) {
            throw new InvalidArgumentException('Recovery grant source must belong to the award.');
        }
    }
}
