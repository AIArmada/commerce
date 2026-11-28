<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment\Api;

use Carbon\CarbonImmutable;

interface RewardEnrollmentConsentApi
{
    /**
     * Require matching historical consent while holding the enrollment and
     * consent row locks in the caller's transaction.
     */
    public function requireConsent(string $programId, string $recipientType, string $recipientId, string $materialHash, CarbonImmutable $decision): RewardEnrollmentConsent;

    public function findConsent(string $programId, string $recipientType, string $recipientId, string $materialHash, CarbonImmutable $decision): ?RewardEnrollmentConsent;

    public function requireMilestoneConsent(string $programId, string $recipientType, string $recipientId, string $materialHash, CarbonImmutable $decision): RewardEnrollmentConsent;

    public function previewConsent(string $programId, string $recipientType, string $recipientId, ?string $materialHash, CarbonImmutable $decision): ?RewardEnrollmentConsentPreview;

    public function coveringJoinedAt(string $enrollmentId, CarbonImmutable $decision): ?CarbonImmutable;
}
