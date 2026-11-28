<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Points\Api;

use AIArmada\Rewards\Contracts\RewardReservationResolver;
use Carbon\CarbonInterface;

interface RewardPointsApi
{
    public function credit(string $recipientType, string $recipientId, string $familyKey, string $unit, int $points, CarbonInterface $availableAt, ?CarbonInterface $expiresAt = null, ?string $awardId = null, ?string $programId = null, ?string $operationKey = null, string $actorType = 'system', ?string $actorId = null, string $reason = 'award-credit', ?CarbonInterface $occurredAt = null): void;

    public function reserve(string $recipientType, string $recipientId, string $familyKey, string $unit, int $points, string $reservationKey, string $reason, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void;

    public function debit(string $recipientType, string $recipientId, string $familyKey, string $unit, int $points, string $operationKey, string $reason, ?string $reservationKey = null, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void;

    public function capture(string $reservationKey, string $recipientType, string $recipientId, string $operationKey, string $reason, CarbonInterface $occurredAt, string $actorType, ?string $actorId): void;

    public function release(string $reservationKey, string $reason, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void;

    public function refund(string $refundOperationKey, string $linkedAggregateKey, int $points, string $reason, ?CarbonInterface $occurredAt = null, string $actorType = 'system', ?string $actorId = null): void;

    public function markSubmission(string $reservationKey, string $status, ?string $reference = null, ?CarbonInterface $at = null): void;

    public function clawBack(string $awardId, string $actorType, ?string $actorId, string $actorRole, string $reason, CarbonInterface $decidedAt, ?RewardReservationResolver $resolver = null): void;

    public function reservationMatches(string $reservationKey, string $familyKey, string $recipientType, string $recipientId, bool $lock): bool;
}
