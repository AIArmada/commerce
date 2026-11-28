<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment\Api;

use Carbon\CarbonImmutable;

final readonly class RewardEnrollmentConsentPreview
{
    public function __construct(
        public bool $activeAtDecision,
        public CarbonImmutable $relevantJoinedAt,
        public ?string $acceptanceId,
        public ?string $materialHash,
        public ?CarbonImmutable $acceptedAt,
        public bool $hasPriorAcceptance,
    ) {}
}
