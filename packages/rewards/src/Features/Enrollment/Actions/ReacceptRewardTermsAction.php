<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Enrollment\Actions;

use AIArmada\Rewards\Features\Enrollment\Models\RewardEnrollment;
use AIArmada\Rewards\Features\Enrollment\Models\RewardEnrollmentAcceptance;
use AIArmada\Rewards\Features\Enrollment\Persistence\RewardEnrollmentGuard;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Prospective terms reacceptance. Always appends a new acceptance row (next
 * seq, even for identical terms) and advances the current pointer. Prior
 * rows are never rewritten, so frozen references stay stable.
 */
final readonly class ReacceptRewardTermsAction
{
    use AsAction;

    public function handle(
        RewardEnrollment | string $enrollment,
        string $versionId,
        ?string $acceptedVia = null,
        ?CarbonInterface $decisionTime = null,
    ): RewardEnrollmentAcceptance {
        if ($acceptedVia !== null && ! in_array($acceptedVia, EnrollRewardMemberAction::ACCEPTED_VIA, true)) {
            throw new InvalidArgumentException('Accepted-via must be one of [' . implode(',', EnrollRewardMemberAction::ACCEPTED_VIA) . '].');
        }

        $decision = $decisionTime === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decisionTime)->setTimezone('UTC');

        $enrollmentId = $enrollment instanceof RewardEnrollment ? (string) $enrollment->getKey() : $enrollment;

        // The program is the tenant boundary: prove the enrollment's program
        // is in the current owner scope before any lock.
        RewardEnrollmentGuard::findOrFail($enrollmentId);

        return DB::transaction(function () use ($enrollmentId, $versionId, $acceptedVia, $decision): RewardEnrollmentAcceptance {
            $locked = RewardEnrollment::query()->whereKey($enrollmentId)->lockForUpdate()->first();

            if (! $locked instanceof RewardEnrollment) {
                throw new InvalidArgumentException('Unknown reward enrollment.');
            }

            $version = app(RewardProgramLockApi::class)->findEnrollmentPolicy((string) $locked->program_id, $versionId);

            if ($version === null) {
                throw new InvalidArgumentException('Policy version does not belong to the enrollment program.');
            }

            $versionPublishedAt = CarbonImmutable::parse($version->publishedAt)->setTimezone('UTC');

            if ($decision->lessThan($versionPublishedAt)) {
                throw new InvalidArgumentException('Acceptance cannot predate the policy publication.');
            }

            $enrolledAt = CarbonImmutable::parse($locked->enrolled_at)->setTimezone('UTC');

            if ($decision->lessThan($enrolledAt)) {
                throw new InvalidArgumentException('Acceptance cannot predate the enrollment.');
            }

            $latest = RewardEnrollmentAcceptance::query()
                ->where('enrollment_id', $locked->getKey())
                ->orderByDesc('accepted_at')
                ->orderByDesc('seq')
                ->lockForUpdate()
                ->first();

            if ($latest instanceof RewardEnrollmentAcceptance
                && $decision->lessThan(CarbonImmutable::parse($latest->accepted_at)->setTimezone('UTC'))
            ) {
                throw new InvalidArgumentException('Acceptance time cannot move backwards; consent history is append-only.');
            }

            $maxSeq = (int) (RewardEnrollmentAcceptance::query()
                ->where('enrollment_id', $locked->getKey())
                ->orderByDesc('seq')
                ->lockForUpdate()
                ->value('seq') ?? 0);

            $terms = $version->terms;

            $acceptance = RewardEnrollmentAcceptance::query()->create([
                'enrollment_id' => $locked->getKey(),
                'seq' => $maxSeq + 1,
                'policy_version_id' => $version->id,
                'material_terms_hash' => $version->materialTermsHash,
                'terms_label' => (string) ($terms['version_label'] ?? 'v' . $version->version),
                'accepted_snapshot' => [
                    'material' => $terms['material'] ?? [],
                    'rules' => $version->rules,
                    'policy_version' => $version->version,
                    'policy_effective_from' => CarbonImmutable::parse($version->effectiveFrom)->setTimezone('UTC')->toIso8601String(),
                ],
                'accepted_at' => $decision,
                'accepted_via' => $acceptedVia,
            ]);

            $locked->forceFill(['current_acceptance_id' => $acceptance->getKey()])->save();

            return $acceptance;
        });
    }
}
