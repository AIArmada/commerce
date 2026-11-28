<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Queries;

use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Features\Benefits\Api\ConsumedRewardBenefit;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitRecoveryApi;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitGrant;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitItem;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use InvalidArgumentException;

final readonly class RewardBenefitRecoveryQuery implements RewardBenefitRecoveryApi
{
    public function __construct(
        private RewardAwardLockApi $awards,
        private RewardProgramLockApi $programs,
    ) {}

    public function consumedGrantsForReversal(string $awardId): array
    {
        $this->awards->assertAwardInScopeWhenExists($awardId);

        $grants = RewardBenefitGrant::query()
            ->where('award_id', $awardId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $grants->load('item');

        foreach ($grants as $grant) {
            $this->assertItemInScope($grant);

            if (in_array($grant->status, [RewardBenefitGrantStatus::Reserved, RewardBenefitGrantStatus::Issued], true)) {
                throw new InvalidArgumentException(
                    'Benefit reversal refuses while grant [' . $grant->grant_key . '] is ['
                    . $grant->status->value . ']; cancel, revoke-unused, or confirm use first.'
                );
            }
        }

        $consumed = $grants->filter(static fn (RewardBenefitGrant $grant): bool => $grant->status === RewardBenefitGrantStatus::Consumed);

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
        $this->awards->assertAwardInScopeWhenExists($awardId);

        $grant = RewardBenefitGrant::query()
            ->whereKey($grantId)
            ->where('award_id', $awardId)
            ->with('item')
            ->lockForUpdate()
            ->first();

        if (! $grant instanceof RewardBenefitGrant) {
            throw new InvalidArgumentException('Recovery grant source must belong to the award.');
        }

        $this->assertItemInScope($grant);
    }

    private function assertItemInScope(RewardBenefitGrant $grant): void
    {
        $item = $grant->item;

        if (! $item instanceof RewardBenefitItem) {
            throw new InvalidArgumentException('Benefit grant is missing its frozen catalog item.');
        }

        $this->programs->assertProgramInScope((string) $item->program_id);
    }
}
