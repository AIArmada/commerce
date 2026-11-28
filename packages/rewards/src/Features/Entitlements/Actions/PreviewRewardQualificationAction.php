<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Actions;

use AIArmada\Rewards\Contracts\RewardPreviewEvaluator;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Data\RewardPreviewContext;
use AIArmada\Rewards\Data\RewardPreviewFixture;
use AIArmada\Rewards\Data\RewardPreviewResult;
use AIArmada\Rewards\Data\RewardProgramStatus;
use AIArmada\Rewards\Data\RewardVerificationLevel;
use AIArmada\Rewards\Features\Enrollment\Api\RewardEnrollmentConsentApi;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use AIArmada\Rewards\Queries\RewardModerationPolicyQuery;
use AIArmada\Rewards\Support\RewardRuleValidator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Point-in-time qualification preview over explicit snapshots.
 *
 * Read-only: loads the program, its effective timeline, and the enrollment
 * snapshot, then runs the pure engine. Supplied fixture evidence is labeled
 * fixture-only and never treated as source integration. Live evaluation must
 * re-resolve under locks and enforce award uniqueness plus cap counters.
 */
final readonly class PreviewRewardQualificationAction
{
    use AsAction;

    public function __construct(
        private ?RewardPreviewEvaluator $evaluator = null,
    ) {}

    /**
     * @param  array<string, mixed>|RewardPreviewFixture  $fixture
     * @param  array{member_type: string, member_id: string}|null  $member
     */
    public function handle(
        string $programId,
        RewardComponent | string $requestedComponent,
        array | RewardPreviewFixture $fixture,
        CarbonInterface $decisionTime,
        ?array $member = null,
    ): RewardPreviewResult {
        $component = $requestedComponent instanceof RewardComponent
            ? $requestedComponent
            : RewardComponent::tryFrom($requestedComponent);

        if (! $component instanceof RewardComponent) {
            throw new InvalidArgumentException('Unknown requested component.');
        }

        $facts = $fixture instanceof RewardPreviewFixture ? $fixture : $this->fixtureFromArray($fixture);
        $decision = CarbonImmutable::parse($decisionTime)->setTimezone('UTC');

        $programs = app(RewardProgramLockApi::class);
        $loaded = $programs->readProgram($programId);
        $applicableVersion = $programs->effectivePolicy($programId, $decision->toIso8601String());
        $rules = $applicableVersion?->rules ?? [];

        $components = [];
        $rawComponents = $rules['components'] ?? [];

        if (is_array($rawComponents)) {
            foreach ($rawComponents as $entry) {
                if (is_array($entry) && is_string($entry['type'] ?? null)) {
                    $components[] = $entry['type'];
                }
            }
        }

        $compatible = $applicableVersion !== null
            ? array_values(array_filter($applicableVersion->compatibleWith, is_string(...)))
            : [];

        $consent = null;

        if ($member !== null) {
            if (! is_string($member['member_type'] ?? null) || ! is_string($member['member_id'] ?? null)) {
                throw new InvalidArgumentException('Member must carry member_type and member_id strings.');
            }

            $consent = app(RewardEnrollmentConsentApi::class)->previewConsent(
                $loaded->id,
                $member['member_type'],
                $member['member_id'],
                $applicableVersion !== null ? $applicableVersion->materialTermsHash : null,
                $decision,
            );
        }

        $context = new RewardPreviewContext(
            programFamilyKey: $loaded->familyKey,
            programStatus: RewardProgramStatus::from($loaded->status),
            versionFound: $applicableVersion !== null,
            versionId: $applicableVersion !== null ? $applicableVersion->id : null,
            versionNumber: $applicableVersion !== null ? $applicableVersion->version : null,
            versionRules: $rules,
            versionComponents: $components,
            versionMaterialHash: $applicableVersion !== null ? $applicableVersion->materialTermsHash : '',
            versionEffectiveFrom: $applicableVersion !== null ? CarbonImmutable::parse($applicableVersion->effectiveFrom)->setTimezone('UTC') : null,
            compatibleWith: $compatible,
            enrollmentExists: $consent !== null,
            enrollmentActiveAtDecision: $consent?->activeAtDecision ?? false,
            acceptanceId: $consent?->acceptanceId,
            acceptanceMaterialHash: $consent?->materialHash,
            acceptanceAcceptedAt: $consent?->acceptedAt,
            hasPriorAcceptance: $consent?->hasPriorAcceptance ?? false,
            relevantPeriodJoinedAt: $consent?->relevantJoinedAt,
            fixture: $facts,
            requestedComponent: $component,
            decisionTime: $decision,
            moderatedOutcomes: RewardModerationPolicyQuery::outcomes(),
        );

        return ($this->evaluator ?? app(RewardPreviewEvaluator::class))->evaluate($context);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fixtureFromArray(array $input): RewardPreviewFixture
    {
        foreach (array_keys($input) as $key) {
            if (! in_array($key, [
                'source_business_key', 'outcome', 'category', 'occurred_at', 'verification',
                'is_first_time', 'actor_id', 'sharer_id', 'is_claimed_identity', 'is_attributed',
                'is_ambiguous_device', 'claimed_family_keys', 'is_bot', 'is_cancelled', 'is_valid_source',
                'beneficiary_role',
            ], true)) {
                throw new InvalidArgumentException("Unknown fixture key [{$key}].");
            }
        }

        $businessKey = $input['source_business_key'] ?? null;

        if (! is_string($businessKey) || $businessKey === '' || mb_strlen($businessKey) > 191) {
            throw new InvalidArgumentException('Fixture source_business_key must be 1..191 characters.');
        }

        $outcome = (string) ($input['outcome'] ?? '');

        if ($outcome === '' || ! in_array($outcome, RewardRuleValidator::outcomes(), true)) {
            throw new InvalidArgumentException('Fixture outcome must be allowlisted.');
        }

        $category = $input['category'] ?? null;

        if ($category !== null && (! is_string($category) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,31}$/', $category) !== 1)) {
            throw new InvalidArgumentException('Fixture category must be a 1..32 slug or null.');
        }

        $rawOccurredAt = $input['occurred_at'] ?? null;

        if (! is_string($rawOccurredAt)
            || preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $rawOccurredAt) !== 1
        ) {
            throw new InvalidArgumentException('Fixture occurred_at must be an explicit absolute timestamp (YYYY-MM-DDTHH:MM...); missing, empty, relative, and malformed dates are rejected without guessing.');
        }

        try {
            $occurredAt = CarbonImmutable::parse($rawOccurredAt, 'UTC')->setTimezone('UTC');
        } catch (Throwable) {
            throw new InvalidArgumentException('Fixture occurred_at must be a valid absolute timestamp.');
        }

        $verification = RewardVerificationLevel::tryFrom((string) ($input['verification'] ?? ''));

        if (! $verification instanceof RewardVerificationLevel) {
            throw new InvalidArgumentException('Fixture verification must be a known level.');
        }

        foreach (['is_first_time', 'is_claimed_identity', 'is_attributed'] as $flag) {
            if (! is_bool($input[$flag] ?? null)) {
                throw new InvalidArgumentException("Fixture [{$flag}] must be boolean.");
            }
        }

        foreach (['actor_id', 'sharer_id'] as $idField) {
            $value = $input[$idField] ?? null;

            if ($value !== null && (! is_string($value) || $value === '' || mb_strlen($value) > 64)) {
                throw new InvalidArgumentException("Fixture [{$idField}] must be null or 1..64 characters.");
            }
        }

        if (array_key_exists('is_ambiguous_device', $input) && ! is_bool($input['is_ambiguous_device'])) {
            throw new InvalidArgumentException('Fixture is_ambiguous_device must be boolean.');
        }

        foreach (['is_bot', 'is_cancelled', 'is_valid_source'] as $validityFlag) {
            if (array_key_exists($validityFlag, $input) && ! is_bool($input[$validityFlag])) {
                throw new InvalidArgumentException("Fixture [{$validityFlag}] must be boolean.");
            }
        }

        $claimed = $input['claimed_family_keys'] ?? [];

        if (! is_array($claimed) || ($claimed !== [] && ! array_is_list($claimed))) {
            throw new InvalidArgumentException('Fixture claimed_family_keys must be a list.');
        }

        foreach ($claimed as $family) {
            if (! is_string($family) || preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/', $family) !== 1) {
                throw new InvalidArgumentException('Fixture claimed_family_keys must be family slugs.');
            }
        }

        $rawBeneficiaryRole = $input['beneficiary_role'] ?? RewardBeneficiaryRole::Recipient->value;

        if (! is_string($rawBeneficiaryRole)) {
            throw new InvalidArgumentException('Fixture beneficiary_role must be a string role value.');
        }

        $beneficiaryRole = RewardBeneficiaryRole::tryFrom($rawBeneficiaryRole);

        if (! $beneficiaryRole instanceof RewardBeneficiaryRole) {
            throw new InvalidArgumentException('Fixture beneficiary_role must be a known role.');
        }

        return new RewardPreviewFixture(
            sourceBusinessKey: $businessKey,
            outcome: $outcome,
            category: $category,
            occurredAt: $occurredAt,
            verification: $verification,
            isFirstTime: $input['is_first_time'],
            actorId: $input['actor_id'] ?? null,
            sharerId: $input['sharer_id'] ?? null,
            isClaimedIdentity: $input['is_claimed_identity'],
            isAttributed: $input['is_attributed'],
            isAmbiguousDevice: (bool) ($input['is_ambiguous_device'] ?? false),
            claimedFamilyKeys: $claimed,
            isBot: (bool) ($input['is_bot'] ?? false),
            isCancelled: (bool) ($input['is_cancelled'] ?? false),
            isValidSource: (bool) ($input['is_valid_source'] ?? true),
            beneficiaryRole: $beneficiaryRole,
        );
    }
}
