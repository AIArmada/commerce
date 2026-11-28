<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Queries;

use AIArmada\Rewards\Features\Entitlements\Api\RewardDecisionHistoryApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAppealDecision;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAwardEntry;
use AIArmada\Rewards\Features\Entitlements\Persistence\RewardAwardGuard;

final readonly class RewardDecisionHistoryQuery implements RewardDecisionHistoryApi
{
    public function awardHistory(string $awardId): array
    {
        RewardAwardGuard::findAwardOrFail($awardId);

        return RewardAwardEntry::query()
            ->where('award_id', $awardId)
            ->orderBy('seq')
            ->get(['from_status', 'to_status', 'reason', 'actor_role', 'decided_at'])
            ->map(static fn (RewardAwardEntry $entry): array => [
                'transition' => $entry->from_status === '' ? 'evaluated' : $entry->from_status . ' → ' . $entry->to_status,
                'reason' => $entry->reason,
                'decided_by' => $entry->actor_role,
                'decided_at' => (string) $entry->decided_at,
            ])
            ->values()
            ->all();
    }

    public function appealHistory(string $appealId): array
    {
        RewardAwardGuard::findAppealOrFail($appealId);

        return RewardAppealDecision::query()
            ->where('appeal_id', $appealId)
            ->orderBy('seq')
            ->get(['from_status', 'to_status', 'reason', 'actor_role', 'decided_at'])
            ->map(static fn (RewardAppealDecision $decision): array => [
                'transition' => $decision->from_status . ' → ' . $decision->to_status,
                'reason' => $decision->reason,
                'decided_by' => $decision->actor_role,
                'decided_at' => (string) $decision->decided_at,
            ])
            ->values()
            ->all();
    }
}
