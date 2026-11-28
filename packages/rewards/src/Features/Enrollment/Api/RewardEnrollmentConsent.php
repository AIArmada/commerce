<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment\Api;

use Carbon\CarbonImmutable;

final readonly class RewardEnrollmentConsent
{
    public function __construct(
        public string $enrollmentId,
        public string $acceptanceId,
        public string $materialHash,
        public string $termsLabel,
        public CarbonImmutable $acceptedAt,
        public CarbonImmutable $joinedAt,
    ) {}
}
