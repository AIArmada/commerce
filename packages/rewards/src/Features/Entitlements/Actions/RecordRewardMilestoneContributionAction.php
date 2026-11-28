<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Actions;

use AIArmada\Rewards\Contracts\RewardGratitudePlanner;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Data\RewardSourceFact;
use AIArmada\Rewards\Data\RewardVerificationLevel;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardMilestoneStatus;
use AIArmada\Rewards\Features\Enrollment\Api\RewardEnrollmentConsentApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Features\Entitlements\Models\RewardMilestoneCycle;
use AIArmada\Rewards\Features\Entitlements\Persistence\RewardLedgerWriter;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Programs\Api\RewardPolicySnapshot;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Persistence\RewardUniqueViolation;
use AIArmada\Rewards\Queries\RewardModerationPolicyQuery;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardMilestoneCycleIdentity;
use AIArmada\Rewards\Support\RewardPointInput;
use AIArmada\Rewards\Support\RewardRuleValidator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Milestone contribution recording with deterministic cycle achievement.
 *
 * Every contribution must name an authorized published milestone definition
 * (program version rules at the fact's earning time), a qualified canonical
 * source fact through the reader boundary, and a covering enrollment and
 * acceptance. Unknown programs, unpublished or mismatched definitions,
 * unknown/unclaimed/self/bot/cancelled/invalid/duplicate/incompatible facts,
 * role-unbound contributions (the fact must bind the contributor role to
 * the recipient), and missing/stale consent are all refused. The cycle
 * identity (recipient, family, milestone_key, fixed window_start) is
 * computable from the fact's earning time. Re-recording the same source
 * returns the existing contribution; reaching the target achieves the
 * cycle and mints exactly one milestone award with frozen
 * policy/enrollment/acceptance provenance (never null aggregates), whose
 * beneficiary_role is the achieving contribution's role. Milestone rewards
 * are recognition/points/badge only; cash/benefit settlement is WP6 scope.
 *
 * @return array{cycle: RewardMilestoneCycle, contribution: RewardMilestoneContribution, achieved: bool}
 */
final readonly class RecordRewardMilestoneContributionAction
{
    use AsAction;

    public function __construct(
        private ?RewardSourceFactReader $sourceReader = null,
    ) {}

    /**
     * @param  array<string, mixed>  $rewardConfig  Must exactly match the published definition reward.
     * @param  array<string, mixed>|null  $sourceSnapshot  Must match the canonical fact snapshot when supplied.
     * @return array{cycle: RewardMilestoneCycle, contribution: RewardMilestoneContribution, achieved: bool}
     */
    public function handle(
        string $recipientType,
        string $recipientId,
        string $familyKey,
        string $milestoneKey,
        int $targetCount,
        int $windowDays,
        RewardComponent | string $rewardComponent,
        array $rewardConfig,
        string $sourceBusinessKey,
        string $contributorType,
        string $contributorId,
        RewardBeneficiaryRole | string $contributorRole,
        string $outcome,
        CarbonInterface $occurredAt,
        string $programId,
        ?array $sourceSnapshot = null,
        ?CarbonInterface $contributedAt = null,
        string $actorType = 'system',
        ?string $actorId = null,
    ): array {
        app(RewardProgramLockApi::class)->assertProgramInScope($programId);

        RewardPointInput::assertRecipient($recipientType, $recipientId);
        RewardPointInput::assertFamily($familyKey);
        RewardPointInput::assertActor($actorType, $actorId);
        RewardMilestoneCycleIdentity::assertDefinition($milestoneKey, $targetCount, $windowDays);

        $component = $rewardComponent instanceof RewardComponent ? $rewardComponent : RewardComponent::tryFrom($rewardComponent);

        if (! $component instanceof RewardComponent) {
            throw new InvalidArgumentException('Unknown milestone reward component.');
        }

        if ($component->requiresMoneyOrBenefitFulfillment()) {
            throw new InvalidArgumentException('Milestone cash/benefit settlement is WP6 finance scope; use recognition/points/badge here.');
        }

        $reward = $this->normalizeReward($component, $rewardConfig);

        if ($sourceBusinessKey === '' || mb_strlen($sourceBusinessKey) > 191) {
            throw new InvalidArgumentException('Source business key must be 1..191 characters.');
        }

        $role = $contributorRole instanceof RewardBeneficiaryRole ? $contributorRole : RewardBeneficiaryRole::tryFrom($contributorRole);

        if (! $role instanceof RewardBeneficiaryRole) {
            throw new InvalidArgumentException('Unknown contributor role.');
        }

        if ($outcome === '' || ! in_array($outcome, RewardRuleValidator::outcomes(), true)) {
            throw new InvalidArgumentException('Contribution outcome must be allowlisted.');
        }

        $outcomeEnum = $outcome;

        if (! Str::isUuid($programId)) {
            throw new InvalidArgumentException('Program id must be a UUID.');
        }

        $allowedContributorTypes = RewardLedgerEntries::recipientTypes();

        if (! in_array($contributorType, $allowedContributorTypes, true) || ! Str::isUuid($contributorId)) {
            throw new InvalidArgumentException('Contributor must be an allowlisted recipient UUID.');
        }

        if ($contributorId !== $recipientId) {
            throw new InvalidArgumentException('Milestone contributions must be the recipient\u2019s own qualifying facts.');
        }

        $occurred = CarbonImmutable::parse($occurredAt)->setTimezone('UTC');
        $at = $contributedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($contributedAt)->setTimezone('UTC');

        if ($occurred->greaterThan($at)) {
            throw new InvalidArgumentException('Contribution occurrence cannot be after its recording time.');
        }

        $reader = $this->sourceReader ?? app(RewardSourceFactReader::class);
        $firstRead = $reader->read($sourceBusinessKey);

        if (! $firstRead instanceof RewardSourceFact) {
            throw new InvalidArgumentException('Refused: unknown_source has no readable source fact.');
        }

        $this->assertFactQualifies($firstRead, $role, $recipientId, $outcomeEnum, $occurred, $sourceSnapshot);

        $windowStart = RewardMilestoneCycleIdentity::windowStart(
            $firstRead->occurredAt->setTimezone('UTC'),
            $windowDays
        );
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(fn (): array => $this->attempt(
                    $recipientType,
                    $recipientId,
                    $familyKey,
                    $milestoneKey,
                    $targetCount,
                    $windowDays,
                    $component,
                    $reward,
                    $sourceBusinessKey,
                    $contributorType,
                    $contributorId,
                    $role,
                    $outcomeEnum,
                    $occurred,
                    $programId,
                    $firstRead,
                    $reader,
                    $at,
                    $actorType,
                    $actorId,
                    $windowStart,
                ));
            } catch (QueryException $exception) {
                if ($attempt >= 3 || (! RewardUniqueViolation::matches($exception) && ! self::isDeadlock($exception))) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $sourceSnapshot
     */
    private function assertFactQualifies(
        RewardSourceFact $fact,
        RewardBeneficiaryRole $role,
        string $recipientId,
        string $outcome,
        CarbonImmutable $occurred,
        ?array $sourceSnapshot,
    ): void {
        if ($fact->outcome !== $outcome) {
            throw new InvalidArgumentException('Contribution outcome must match the canonical source fact.');
        }

        if ($fact->occurredAt->setTimezone('UTC')->toIso8601String() !== $occurred->setTimezone('UTC')->toIso8601String()) {
            throw new InvalidArgumentException('Contribution occurrence must match the canonical source fact; no backdated earning authority.');
        }

        if ($sourceSnapshot !== null && $sourceSnapshot !== $fact->snapshot()) {
            throw new InvalidArgumentException('Contribution snapshot must match the canonical source fact.');
        }

        if (! $fact->isValid) {
            throw new InvalidArgumentException('Refused: source_invalid contributions never count.');
        }

        if ($fact->isBot) {
            throw new InvalidArgumentException('Refused: source_bot_excluded contributions never count.');
        }

        if ($fact->isCancelled) {
            throw new InvalidArgumentException('Refused: source_cancelled contributions never count.');
        }

        if ($fact->correctionStatus->value === 'withdrawn') {
            throw new InvalidArgumentException('Refused: source_withdrawn contributions never count.');
        }

        if ($fact->fraudStatus->value === 'confirmed') {
            throw new InvalidArgumentException('Refused: fraud_confirmed contributions never count.');
        }

        if (! $fact->isClaimedIdentity) {
            throw new InvalidArgumentException('Refused: unclaimed guest facts never count.');
        }

        if (! $fact->isAttributed && $role->requiresAttribution()) {
            throw new InvalidArgumentException('Refused: unattributed facts never count for a sharer-bound contributor role.');
        }

        if ($fact->actorId !== null && $fact->sharerId !== null && $fact->actorId === $fact->sharerId) {
            throw new InvalidArgumentException('Refused: self-credited facts never count.');
        }

        if (! $role->binds($fact->actorId, $fact->actorRole, $fact->sharerId, $recipientId)) {
            throw new InvalidArgumentException('Refused: contributing fact does not bind the contributor role to the recipient.');
        }
    }

    /**
     * @param  array<string, mixed>  $rewardConfig
     * @return array<string, mixed>
     */
    private function normalizeReward(RewardComponent $component, array $rewardConfig): array
    {
        return match ($component) {
            RewardComponent::Points => $this->normalizePoints($rewardConfig),
            RewardComponent::Badge => $this->normalizeBadge($rewardConfig),
            RewardComponent::Recognition => [
                'type' => 'recognition',
                'template' => is_string($rewardConfig['template'] ?? null) ? $rewardConfig['template'] : 'quiet-thanks',
            ],
            default => throw new InvalidArgumentException('Milestone reward component must be recognition, points, or badge.'),
        };
    }

    /**
     * @param  array<string, mixed>  $rewardConfig
     * @return array<string, mixed>
     */
    private function normalizePoints(array $rewardConfig): array
    {
        $points = $rewardConfig['points'] ?? null;
        $unit = $rewardConfig['unit'] ?? null;

        if (! is_int($points) || $points < 1 || $points > RewardRuleValidator::maxPoints()) {
            throw new InvalidArgumentException('Milestone points must be an integer 1..' . RewardRuleValidator::maxPoints() . '.');
        }

        if (! is_string($unit)) {
            throw new InvalidArgumentException('Milestone points require a unit.');
        }

        RewardPointInput::assertUnit($unit);

        return ['type' => 'points', 'points' => $points, 'unit' => $unit];
    }

    /**
     * @param  array<string, mixed>  $rewardConfig
     * @return array<string, mixed>
     */
    private function normalizeBadge(array $rewardConfig): array
    {
        $badgeKey = $rewardConfig['badge_key'] ?? null;

        if (! is_string($badgeKey) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $badgeKey) !== 1) {
            throw new InvalidArgumentException('Milestone badge_key must be a 1..64 slug.');
        }

        return ['type' => 'badge', 'badge_key' => $badgeKey];
    }

    /**
     * @param  array<string, mixed>  $frozen
     * @param  array<string, mixed>  $reward
     */
    private function snapshotsMatch(array $frozen, array $reward): bool
    {
        return $this->canonicalizeSnapshot($frozen) === $this->canonicalizeSnapshot($reward);
    }

    /**
     * JSON object key order is not semantic, and JSONB storage normalizes
     * it. Canonicalize associative maps recursively before strict
     * comparison, while preserving list order and scalar types.
     */
    private function canonicalizeSnapshot(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeSnapshot($item);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $reward
     * @return array{cycle: RewardMilestoneCycle, contribution: RewardMilestoneContribution, achieved: bool}
     */
    private function attempt(
        string $recipientType,
        string $recipientId,
        string $familyKey,
        string $milestoneKey,
        int $targetCount,
        int $windowDays,
        RewardComponent $component,
        array $reward,
        string $sourceBusinessKey,
        string $contributorType,
        string $contributorId,
        RewardBeneficiaryRole $role,
        string $outcomeEnum,
        CarbonImmutable $occurred,
        string $programId,
        RewardSourceFact $firstRead,
        RewardSourceFactReader $reader,
        CarbonImmutable $at,
        string $actorType,
        ?string $actorId,
        CarbonImmutable $windowStart,
    ): array {
        $programs = app(RewardProgramLockApi::class);
        $program = $programs->readProgram($programId, lock: true);

        if ($program->familyKey !== $familyKey) {
            throw new InvalidArgumentException('Milestone family must match the program family; cross-program definitions never mix.');
        }

        if ($program->status !== 'active') {
            throw new InvalidArgumentException('Refused: program_not_active stops new milestone qualification.');
        }

        $factOccurred = $firstRead->occurredAt->setTimezone('UTC');

        $version = $programs->effectivePolicy($programId, $factOccurred->toIso8601String());

        if ($version === null) {
            throw new InvalidArgumentException('Refused: version_missing has no published version at the earning time.');
        }

        $published = $this->publishedDefinition($version, $milestoneKey);

        if ($published === null) {
            throw new InvalidArgumentException('Refused: milestone definition is not published in the applicable version.');
        }

        if ((int) $published['target_count'] !== $targetCount
            || (int) $published['window_days'] !== $windowDays
            || $this->normalizeReward($component, (array) $published['reward']) !== $reward
        ) {
            throw new InvalidArgumentException('Milestone definition mismatches the published definition.');
        }

        $this->assertOutcomeCompatible($version, $published, $outcomeEnum, $firstRead, $role);

        $coveringAcceptance = app(RewardEnrollmentConsentApi::class)->requireMilestoneConsent(
            $program->id,
            $recipientType,
            $recipientId,
            $version->materialTermsHash,
            $factOccurred,
        );

        $secondRead = $reader->read($sourceBusinessKey);

        if (! $secondRead instanceof RewardSourceFact || $secondRead->snapshot() !== $firstRead->snapshot()) {
            throw new InvalidArgumentException('Source fact changed during recording; retry with a fresh read.');
        }

        $cycle = RewardMilestoneCycle::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('family_key', $familyKey)
            ->where('milestone_key', $milestoneKey)
            ->where('window_start', $windowStart)
            ->lockForUpdate()
            ->first();

        if (! $cycle instanceof RewardMilestoneCycle) {
            $cycle = RewardMilestoneCycle::query()->create([
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'family_key' => $familyKey,
                'program_id' => $programId,
                'milestone_key' => $milestoneKey,
                'window_start' => $windowStart,
                'window_days' => $windowDays,
                'target_count' => $targetCount,
                'current_count' => 0,
                'reward_snapshot' => $reward,
                'status' => RewardMilestoneStatus::Open,
                'award_id' => null,
                'achieved_at' => null,
                'invalidated_at' => null,
            ]);
        }

        if ((string) $cycle->program_id !== $programId) {
            throw new InvalidArgumentException('Milestone cycle belongs to a different program; cross-program definitions never mix.');
        }

        if ($cycle->target_count !== $targetCount
            || $cycle->window_days !== $windowDays
            || ! $this->snapshotsMatch($cycle->reward_snapshot, $reward)
        ) {
            throw new InvalidArgumentException('Milestone definition mismatches the frozen cycle; a cycle keeps one definition.');
        }

        $existingContribution = RewardMilestoneContribution::query()
            ->where('cycle_id', $cycle->getKey())
            ->where('source_business_key', $sourceBusinessKey)
            ->lockForUpdate()
            ->first();

        if ($existingContribution instanceof RewardMilestoneContribution) {
            return ['cycle' => $cycle->refresh(), 'contribution' => $existingContribution, 'achieved' => false];
        }

        $contribution = RewardMilestoneContribution::query()->create([
            'cycle_id' => $cycle->getKey(),
            'source_business_key' => $sourceBusinessKey,
            'contributor_type' => $contributorType,
            'contributor_id' => $contributorId,
            'contributor_role' => $role->value,
            'outcome' => $outcomeEnum,
            'source_snapshot' => $firstRead->snapshot(),
            'occurred_at' => $factOccurred,
            'contributed_at' => $at,
            'revoked_at' => null,
            'revoke_reason' => null,
        ]);

        $count = RewardMilestoneContribution::query()
            ->where('cycle_id', $cycle->getKey())
            ->whereNull('revoked_at')
            ->count();

        $cycle->forceFill(['current_count' => $count])->save();

        if ($count < $targetCount || $cycle->status !== RewardMilestoneStatus::Open) {
            return ['cycle' => $cycle->refresh(), 'contribution' => $contribution, 'achieved' => false];
        }

        $milestoneSource = RewardMilestoneCycleIdentity::milestoneSourceKey($familyKey, $milestoneKey, $windowStart);

        $existingAward = RewardAward::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('source_business_key', $milestoneSource)
            ->where('family_key', $familyKey)
            ->where('component', $component->value)
            ->lockForUpdate()
            ->first();

        if ($existingAward instanceof RewardAward) {
            $cycle->forceFill([
                'status' => RewardMilestoneStatus::Achieved,
                'award_id' => $cycle->award_id ?? $existingAward->getKey(),
                'achieved_at' => $cycle->achieved_at ?? $at,
            ])->save();

            try {
                app(RewardGratitudePlanner::class)->planFor($recipientType, $recipientId, ['milestones'], $at);
            } catch (InvalidArgumentException) {
                // No slot for this recipient; the achievement stands.
            }

            return ['cycle' => $cycle->refresh(), 'contribution' => $contribution, 'achieved' => true];
        }

        $award = RewardAward::query()->create([
            'program_id' => $programId,
            'policy_version_id' => $version->id,
            'enrollment_id' => $coveringAcceptance->enrollmentId,
            'acceptance_id' => $coveringAcceptance->acceptanceId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'beneficiary_role' => $role,
            'source_business_key' => $milestoneSource,
            'family_key' => $familyKey,
            'component' => $component,
            'status' => RewardAwardStatus::Approved,
            'amount_snapshot' => $reward,
            'policy_snapshot' => [
                'policy_version_id' => $version->id,
                'policy_version' => $version->version,
                'milestone_key' => $milestoneKey,
                'target_count' => $targetCount,
                'window_days' => $windowDays,
                'window_start' => $windowStart->toIso8601String(),
            ],
            'consent_snapshot' => [
                'acceptance_id' => $coveringAcceptance->acceptanceId,
                'accepted_at' => $coveringAcceptance->acceptedAt->toIso8601String(),
                'material_terms_hash' => $coveringAcceptance->materialHash,
                'terms_label' => $coveringAcceptance->termsLabel,
            ],
            'source_snapshot' => [
                'cycle_id' => (string) $cycle->getKey(),
                'contributing_sources' => RewardMilestoneContribution::query()
                    ->where('cycle_id', $cycle->getKey())
                    ->whereNull('revoked_at')
                    ->orderBy('source_business_key')
                    ->pluck('source_business_key')
                    ->all(),
            ],
            'approved_at' => $at,
        ]);

        RewardLedgerWriter::appendAwardEntry(
            $award,
            'none',
            RewardAwardStatus::Approved->value,
            $actorType,
            $actorId,
            'system',
            'milestone-achieved',
            ['milestone_key' => $milestoneKey, 'contributions' => $count],
            null,
            $at,
        );

        $cycle->forceFill([
            'status' => RewardMilestoneStatus::Achieved,
            'award_id' => $award->getKey(),
            'achieved_at' => $at,
        ])->save();

        try {
            app(RewardGratitudePlanner::class)->planFor($recipientType, $recipientId, ['milestones'], $at);
        } catch (InvalidArgumentException) {
            // No slot for this recipient; the achievement stands.
        }

        return ['cycle' => $cycle->refresh(), 'contribution' => $contribution, 'achieved' => true];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publishedDefinition(RewardPolicySnapshot $version, string $milestoneKey): ?array
    {
        $milestones = $version->rules['milestones'] ?? null;

        if (! is_array($milestones)) {
            return null;
        }

        foreach ($milestones as $definition) {
            if (is_array($definition) && ($definition['key'] ?? null) === $milestoneKey) {
                return $definition;
            }
        }

        return null;
    }

    private function assertOutcomeCompatible(
        RewardPolicySnapshot $version,
        array $milestone,
        string $outcome,
        RewardSourceFact $fact,
        RewardBeneficiaryRole $role,
    ): void {
        $rules = $version->rules;
        $eligibility = is_array($rules['eligibility'] ?? null) ? $rules['eligibility'] : [];
        $outcomes = is_array($eligibility['outcomes'] ?? null) ? $eligibility['outcomes'] : [];
        $milestoneOutcomes = is_array($milestone['outcomes'] ?? null) ? $milestone['outcomes'] : $outcomes;

        if (($outcomes !== [] && ! in_array($outcome, $outcomes, true))
            || ($milestoneOutcomes !== [] && ! in_array($outcome, $milestoneOutcomes, true))
        ) {
            throw new InvalidArgumentException('Refused: incompatible outcome is not eligible under the published version.');
        }

        $roles = is_array($milestone['roles'] ?? null) ? $milestone['roles'] : [];

        if ($roles !== [] && ! in_array($role->value, $roles, true)) {
            throw new InvalidArgumentException('Refused: contributor role is not eligible for this milestone.');
        }

        $minimum = RewardVerificationLevel::tryFrom((string) ($milestone['min_verification'] ?? $eligibility['min_verification'] ?? ''));

        if ($minimum instanceof RewardVerificationLevel
            && ! $fact->verification->satisfiesFor($minimum, $outcome, RewardModerationPolicyQuery::outcomes())
        ) {
            throw new InvalidArgumentException('Refused: contributing fact verification is below the published minimum.');
        }

        if (($milestone['first_only'] ?? false) === true && ! $fact->isFirstTime) {
            throw new InvalidArgumentException('Refused: this milestone accepts first-time facts only.');
        }
    }

    private static function isDeadlock(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if (in_array($code, ['40001', '1213'], true)) {
            return true;
        }

        return mb_stripos($exception->getMessage(), 'deadlock') !== false;
    }
}
