<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Persistence;

use AIArmada\Rewards\Features\Entitlements\Models\RewardAppeal;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAppealDecision;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAwardEntry;
use Carbon\CarbonImmutable;

final class RewardLedgerWriter
{
    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public static function appendAwardEntry(
        RewardAward $award,
        string $fromStatus,
        string $toStatus,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        string $reason,
        ?array $evidence,
        ?string $sourceOperationId,
        CarbonImmutable $decidedAt,
    ): RewardAwardEntry {
        $maxSeq = (int) (RewardAwardEntry::query()
            ->where('award_id', $award->getKey())
            ->orderByDesc('seq')
            ->lockForUpdate()
            ->value('seq') ?? 0);

        return RewardAwardEntry::query()->create([
            'award_id' => $award->getKey(),
            'seq' => $maxSeq + 1,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'reason' => $reason,
            'evidence' => $evidence,
            'source_operation_id' => $sourceOperationId,
            'decided_at' => $decidedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public static function appendAppealDecision(
        RewardAppeal $appeal,
        string $fromStatus,
        string $toStatus,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        string $reason,
        ?array $evidence,
        CarbonImmutable $decidedAt,
    ): RewardAppealDecision {
        $maxSeq = (int) (RewardAppealDecision::query()
            ->where('appeal_id', $appeal->getKey())
            ->orderByDesc('seq')
            ->lockForUpdate()
            ->value('seq') ?? 0);

        return RewardAppealDecision::query()->create([
            'appeal_id' => $appeal->getKey(),
            'seq' => $maxSeq + 1,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'reason' => $reason,
            'evidence' => $evidence,
            'decided_at' => $decidedAt,
        ]);
    }
}
