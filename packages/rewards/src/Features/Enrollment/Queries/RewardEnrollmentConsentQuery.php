<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment\Queries;

use AIArmada\Rewards\Features\Enrollment\Api\RewardEnrollmentConsent;
use AIArmada\Rewards\Features\Enrollment\Api\RewardEnrollmentConsentApi;
use AIArmada\Rewards\Models\RewardEnrollment;
use AIArmada\Rewards\Models\RewardEnrollmentAcceptance;
use AIArmada\Rewards\Models\RewardEnrollmentPeriod;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RewardEnrollmentConsentQuery implements RewardEnrollmentConsentApi
{
    public function requireConsent(string $programId, string $recipientType, string $recipientId, string $materialHash, CarbonImmutable $decision): RewardEnrollmentConsent
    {
        return $this->resolve($programId, $recipientType, $recipientId, $materialHash, $decision, true)
            ?? throw new InvalidArgumentException('Refused: enrollment consent could not be resolved.');
    }

    public function findConsent(string $programId, string $recipientType, string $recipientId, string $materialHash, CarbonImmutable $decision): ?RewardEnrollmentConsent
    {
        return $this->resolve($programId, $recipientType, $recipientId, $materialHash, $decision, false);
    }

    public function coveringJoinedAt(string $enrollmentId, CarbonImmutable $decision): ?CarbonImmutable
    {
        return $this->joinedAt($enrollmentId, $decision, false);
    }

    private function resolve(string $programId, string $recipientType, string $recipientId, string $materialHash, CarbonImmutable $decision, bool $lock): ?RewardEnrollmentConsent
    {
        $query = RewardEnrollment::query()
            ->where('program_id', $programId)
            ->where('member_type', $recipientType)
            ->where('member_id', $recipientId)
            ->orderByDesc('enrolled_at');
        $enrollment = $lock ? $query->lockForUpdate()->first() : $query->first();

        if (! $enrollment instanceof RewardEnrollment) {
            if ($lock) {
                throw new InvalidArgumentException('Refused: enrollment_missing for this recipient.');
            }

            return null;
        }

        $enrollmentId = (string) $enrollment->getKey();
        $joinedAt = $this->joinedAt($enrollmentId, $decision, $lock);

        if ($joinedAt === null) {
            if ($lock) {
                throw new InvalidArgumentException('Refused: enrollment_left stops new qualification; accrued obligations keep frozen terms.');
            }

            return null;
        }

        $acceptances = RewardEnrollmentAcceptance::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('accepted_at', '<=', $decision);
        $matching = (clone $acceptances)
            ->where('material_terms_hash', $materialHash)
            ->orderByDesc('accepted_at')
            ->orderByDesc('seq');
        $acceptance = $lock ? $matching->lockForUpdate()->first() : $matching->first();

        if (! $acceptance instanceof RewardEnrollmentAcceptance) {
            if ($lock) {
                throw new InvalidArgumentException($acceptances->exists()
                    ? 'Refused: acceptance_stale_material_change requires prospective reacceptance first.'
                    : 'Refused: acceptance_missing covers no terms at the decision time.');
            }

            return null;
        }

        return new RewardEnrollmentConsent(
            $enrollmentId,
            (string) $acceptance->getKey(),
            $acceptance->material_terms_hash,
            $acceptance->terms_label,
            $acceptance->accepted_at->setTimezone('UTC'),
            $joinedAt,
        );
    }

    private function joinedAt(string $enrollmentId, CarbonImmutable $decision, bool $lock): ?CarbonImmutable
    {
        $query = RewardEnrollmentPeriod::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('joined_at', '<=', $decision)
            ->where(function ($query) use ($decision): void {
                $query->whereNull('left_at')->orWhere('left_at', '>', $decision);
            })
            ->orderByDesc('joined_at')
            ->orderByDesc('seq');
        $period = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $period instanceof RewardEnrollmentPeriod
            ? $period->joined_at->setTimezone('UTC')
            : null;
    }
}
