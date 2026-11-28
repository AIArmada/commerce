<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Points\Actions;

use AIArmada\Rewards\Contracts\RewardReservationResolver;
use AIArmada\Rewards\Features\Points\Api\RewardPointsApi;
use AIArmada\Rewards\Features\Points\Persistence\RewardPointOperationAuthority;
use AIArmada\Rewards\Features\Points\Models\RewardPointOperation;
use Carbon\CarbonInterface;

final readonly class ManageRewardPoints implements RewardPointsApi
{
    public function credit(string $recipientType, string $recipientId, string $familyKey, string $unit, int $points, CarbonInterface $availableAt, ?CarbonInterface $expiresAt = null, ?string $awardId = null, ?string $programId = null, ?string $operationKey = null, string $actorType = 'system', ?string $actorId = null, string $reason = 'award-credit', ?CarbonInterface $occurredAt = null): void
    {
        CreditRewardPointsAction::run($recipientType, $recipientId, $familyKey, $unit, $points, $availableAt, $expiresAt, $awardId, $programId, $operationKey, $actorType, $actorId, $reason, $occurredAt);
    }

    public function reserve(string $recipientType, string $recipientId, string $familyKey, string $unit, int $points, string $reservationKey, string $reason, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void
    {
        ReserveRewardPointsAction::run($recipientType, $recipientId, $familyKey, $unit, $points, $reservationKey, $reason, $occurredAt, $actorType, $actorId);
    }

    public function debit(string $recipientType, string $recipientId, string $familyKey, string $unit, int $points, string $operationKey, string $reason, ?string $reservationKey = null, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void
    {
        DebitRewardPointsAction::run($recipientType, $recipientId, $familyKey, $unit, $points, $operationKey, $reason, $reservationKey, $occurredAt, $actorType, $actorId);
    }

    public function release(string $reservationKey, string $reason, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void
    {
        ReleaseRewardPointsAction::run($reservationKey, $reason, $occurredAt, $actorType, $actorId);
    }

    public function refund(string $refundOperationKey, string $linkedAggregateKey, int $points, string $reason, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void
    {
        RefundRewardPointsAction::run($refundOperationKey, $linkedAggregateKey, $points, $reason, $occurredAt, $actorType, $actorId);
    }

    public function markSubmission(string $reservationKey, string $status, ?string $reference = null, ?CarbonInterface $at = null): void
    {
        MarkRewardReservationSubmittedAction::run($reservationKey, $status, $reference, $at);
    }

    public function clawBack(string $awardId, string $actorType, ?string $actorId, string $actorRole, string $reason, CarbonInterface $decidedAt, ?RewardReservationResolver $resolver = null): void
    {
        (new ClawBackAwardPointsAction($resolver))->handle($awardId, $actorType, $actorId, $actorRole, $reason, $decidedAt);
    }

    public function reservationMatches(string $reservationKey, string $familyKey, string $recipientType, string $recipientId, bool $lock): bool
    {
        $query = RewardPointOperation::query()
            ->where('kind', RewardPointOperationAuthority::KIND_RESERVE)
            ->where('operation_key', $reservationKey);

        $operation = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $operation instanceof RewardPointOperation
            && (string) $operation->family_key === $familyKey
            && (string) $operation->recipient_type === $recipientType
            && (string) $operation->recipient_id === $recipientId
            && (string) $operation->unit !== '';
    }
}
