<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Queries;

use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardDecision;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Support\RewardProgramGuard;
use InvalidArgumentException;

final readonly class RewardAwardLockQuery implements RewardAwardLockApi
{
    public function findAwardDecision(string $awardId, bool $lock = false): ?RewardAwardDecision
    {
        $query = RewardAward::query()->whereKey($awardId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $award = $query->first(['id', 'program_id', 'recipient_id', 'status']);

        if ($award === null) {
            return null;
        }

        RewardProgramGuard::findProgramOrFail((string) $award->program_id);

        return new RewardAwardDecision(
            id: (string) $award->getKey(),
            recipientId: (string) $award->recipient_id,
            status: $award->status->value,
        );
    }

    public function lockScopedAward(string $awardId): void
    {
        RewardProgramGuard::findAwardOrFail($awardId);

        if (! RewardAward::query()->whereKey($awardId)->lockForUpdate()->first(['id']) instanceof RewardAward) {
            throw new InvalidArgumentException('Unknown reward award.');
        }
    }

    public function lockAndFindReversedAwards(array $awardIds): array
    {
        if ($awardIds === []) {
            return [];
        }

        $awardIds = array_values(array_unique($awardIds));

        $awards = RewardAward::query()
            ->whereIn('id', $awardIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'status']);

        if ($awards->count() !== count($awardIds)) {
            throw new InvalidArgumentException('Linked debit award is missing.');
        }

        return $awards
            ->filter(static fn (RewardAward $award): bool => $award->status === RewardAwardStatus::Reversed)
            ->map(static fn (RewardAward $award): string => (string) $award->getKey())
            ->values()
            ->all();
    }
}
