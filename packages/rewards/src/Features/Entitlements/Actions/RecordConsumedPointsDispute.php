<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Actions;

use AIArmada\Rewards\Enums\RewardAdjustmentKind;
use AIArmada\Rewards\Features\Entitlements\Api\RewardDisputeApi;
use AIArmada\Rewards\Models\RewardAdjustment;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Support\RewardOperationKeys;
use AIArmada\Rewards\Support\RewardProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RecordConsumedPointsDispute implements RewardDisputeApi
{
    /** @param array<string, mixed> $amountSnapshot */
    public function recordConsumedPointsDispute(string $awardId, array $amountSnapshot, string $actorType, ?string $actorId, string $actorRole, string $reason, CarbonImmutable $decided): string
    {
        RewardProgramGuard::findAwardOrFail($awardId);

        return DB::transaction(static function () use ($awardId, $amountSnapshot, $actorType, $actorId, $actorRole, $reason, $decided): string {
            RewardAward::query()->whereKey($awardId)->lockForUpdate()->firstOrFail();
            $operationKey = RewardOperationKeys::adjustment('dispute', 'award', $awardId, 'consumed');
            $existing = RewardAdjustment::query()->where('operation_key', $operationKey)->lockForUpdate()->first();

            if ($existing instanceof RewardAdjustment) {
                if ($existing->kind !== RewardAdjustmentKind::Dispute || (string) $existing->award_id !== $awardId) {
                    throw new InvalidArgumentException('Consumed points dispute key is bound to a different award or adjustment kind.');
                }

                return (string) $existing->getKey();
            }

            $adjustment = AppendRewardAdjustmentAction::run(
                RewardAdjustmentKind::Dispute,
                $awardId,
                null,
                null,
                $operationKey,
                $amountSnapshot,
                $actorType,
                $actorId,
                $actorRole,
                'reversal-consumed-value:' . $reason,
                ['note' => 'Net consumed value is not restored; human settlement required. Gross spend and linked refunds stay in history.'],
                $decided,
            );

            return (string) $adjustment->getKey();
        });
    }
}
