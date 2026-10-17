<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\RecordRewardMilestoneContributionAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardEvaluationOutcome;
use AIArmada\Rewards\Enums\RewardMilestoneStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardVerificationLevel;
use AIArmada\Rewards\Jobs\EvaluateRewardEntitlementJob;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardMilestoneCycle;
use AIArmada\Rewards\Support\RewardEvaluationCandidates;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function roleMilestoneProgram(string $prefix, string $key, int $target, array $criteria = []): array
{
    [$program, $version, $enrollment, $memberId] = RewardLedgerFixtures::seedActiveEnrolled(
        $prefix,
        RewardProgramMode::Points,
        ['milestones' => [array_merge([
            'key' => $key,
            'target_count' => $target,
            'window_days' => 30,
            'reward' => ['type' => 'points', 'points' => 25, 'unit' => 'pts'],
        ], $criteria)]],
    );

    return [$program->refresh(), $memberId];
}

function roleMilestoneRecord(
    FakeRewardSourceFactReader $reader,
    string $recipient,
    string $family,
    string $key,
    string $source,
    string $occurred,
    string $programId,
    RewardBeneficiaryRole $role,
    array $factOverrides = [],
): array {
    $reader->seed(RewardLedgerFixtures::fact($source, $recipient, $occurred, 'attendance', $factOverrides));

    return (new RecordRewardMilestoneContributionAction($reader))->handle(
        'user',
        $recipient,
        $family,
        $key,
        2,
        30,
        RewardComponent::Points,
        ['points' => 25, 'unit' => 'pts'],
        $source,
        'user',
        $recipient,
        $role,
        'attendance',
        CarbonImmutable::parse($occurred),
        $programId,
    );
}

it('emits actor-side candidates for unattributed facts and drops the sharer side', function (): void {
    RewardLedgerFixtures::seedActiveEnrolled('role-cand');
    $at = CarbonImmutable::parse('2026-06-15T00:00:00Z');

    $actorId = (string) Str::uuid();
    $sharerId = (string) Str::uuid();

    $unattributed = RewardLedgerFixtures::fact('src-cand-1', $sharerId, '2026-06-12T00:00:00Z', 'attendance', [
        'actorId' => $actorId,
        'isAttributed' => false,
    ]);

    $candidates = RewardEvaluationCandidates::for($unattributed, $at);

    expect($candidates)->not->toBeEmpty();

    foreach ($candidates as $candidate) {
        expect($candidate['role'])->toBe(RewardBeneficiaryRole::Participant)
            ->and($candidate['recipient_id'])->toBe($actorId);
    }

    $staffFact = RewardLedgerFixtures::fact('src-cand-2', $sharerId, '2026-06-12T00:00:00Z', 'attendance', [
        'actorId' => $actorId,
        'actorRole' => RewardBeneficiaryRole::Staff,
        'isAttributed' => false,
    ]);

    $staffCandidates = RewardEvaluationCandidates::for($staffFact, $at);

    expect($staffCandidates)->not->toBeEmpty();

    foreach ($staffCandidates as $candidate) {
        expect($candidate['role'])->toBe(RewardBeneficiaryRole::Staff)
            ->and($candidate['recipient_id'])->toBe($actorId);
    }

    // A Staff actor role must not collapse to Participant.
    expect(array_unique(array_map(
        static fn (array $candidate): string => $candidate['role']->value,
        $staffCandidates,
    )))->toBe([RewardBeneficiaryRole::Staff->value]);

    // Attributed facts still fan out to both sides.
    $attributed = RewardLedgerFixtures::fact('src-cand-3', $sharerId, '2026-06-12T00:00:00Z', 'attendance', [
        'actorId' => $actorId,
    ]);

    $roles = array_values(array_unique(array_map(
        static fn (array $candidate): string => $candidate['role']->value,
        RewardEvaluationCandidates::for($attributed, $at),
    )));
    sort($roles);

    expect($roles)->toBe([RewardBeneficiaryRole::Participant->value, RewardBeneficiaryRole::Sharer->value]);

    // Invalid and cancelled facts stay silent even when actor-bound.
    foreach ([['isValid' => false], ['isCancelled' => true]] as $index => $override) {
        $silent = RewardLedgerFixtures::fact('src-cand-silent-' . $index, $sharerId, '2026-06-12T00:00:00Z', 'attendance', [
            'actorId' => $actorId,
            ...$override,
        ]);

        expect(RewardEvaluationCandidates::for($silent, $at))->toBe([]);
    }
});

it('does not freeze awards for a fact side excluded by every published rule', function (): void {
    [$program, , , $participantId] = RewardLedgerFixtures::seedActiveEnrolled(
        'role-excluded-side',
        RewardProgramMode::Points,
        [
            'components' => [[
                'type' => 'points',
                'variants' => [[
                    'outcomes' => ['attendance'],
                    'roles' => [RewardBeneficiaryRole::Participant->value],
                    'min_verification' => 'accepted',
                    'first_only' => false,
                    'points' => 9,
                    'unit' => 'pts',
                ]],
            ]],
            'eligibility' => [
                'outcomes' => ['attendance'],
                'min_verification' => 'accepted',
                'first_only' => false,
            ],
            'milestones' => [],
        ],
    );
    $sharerId = (string) Str::uuid();
    $fact = RewardLedgerFixtures::fact('src-role-excluded-side', $sharerId, '2026-06-12T00:00:00Z', 'attendance', [
        'actorId' => $participantId,
        'sharerId' => $sharerId,
        'isAttributed' => true,
        'verification' => RewardVerificationLevel::StaffVerified,
    ]);
    $reader = new FakeRewardSourceFactReader;
    $reader->seed($fact);
    $candidates = RewardEvaluationCandidates::for($fact, CarbonImmutable::parse('2026-06-15T00:00:00Z'));

    expect(array_map(
        static fn (array $candidate): RewardBeneficiaryRole => $candidate['role'],
        $candidates,
    ))->toBe([RewardBeneficiaryRole::Participant]);

    (new EvaluateRewardEntitlementJob($fact->sourceBusinessKey))->handle(
        $reader,
        new EvaluateRewardEntitlementAction($reader),
    );

    $awards = RewardAward::query()->where('program_id', $program->getKey())->get();

    expect($awards)->toHaveCount(1)
        ->and($awards->sole()->beneficiary_role)->toBe(RewardBeneficiaryRole::Participant)
        ->and($awards->where('beneficiary_role', RewardBeneficiaryRole::Sharer))->toBeEmpty();
});

it('skips incompatible early milestones and grants later tiers for their own role', function (): void {
    config(['rewards.outcomes' => ['signup', 'attendance']]);

    $milestones = [];
    foreach ([
        ['key' => 'tier-1', 'outcomes' => ['signup'], 'points' => 10],
        ['key' => 'tier-2', 'outcomes' => ['attendance'], 'points' => 20],
        ['key' => 'tier-3', 'outcomes' => ['attendance'], 'points' => 30],
        ['key' => 'tier-4', 'outcomes' => ['attendance'], 'points' => 40],
        ['key' => 'tier-5', 'outcomes' => ['attendance'], 'points' => 50],
    ] as $milestone) {
        $milestones[] = [
            'key' => $milestone['key'],
            'label' => ucfirst($milestone['key']),
            'outcomes' => $milestone['outcomes'],
            'roles' => [RewardBeneficiaryRole::Sharer->value],
            'min_verification' => 'accepted',
            'first_only' => false,
            'target_count' => 1,
            'window_days' => 365,
            'reward' => ['type' => 'points', 'points' => $milestone['points'], 'unit' => 'pts'],
        ];
    }

    [$program, $version, , $participantId] = RewardLedgerFixtures::seedActiveEnrolled(
        'role-multi-tier',
        RewardProgramMode::Points,
        [
            'components' => [[
                'type' => 'points',
                'variants' => [[
                    'outcomes' => ['attendance'],
                    'roles' => [RewardBeneficiaryRole::Participant->value],
                    'min_verification' => 'accepted',
                    'first_only' => false,
                    'points' => 7,
                    'unit' => 'pts',
                ]],
            ]],
            'eligibility' => [
                'outcomes' => ['signup', 'attendance'],
                'min_verification' => 'accepted',
                'first_only' => false,
            ],
            'milestones' => $milestones,
        ],
    );

    $sharerId = (string) Str::uuid();
    EnrollRewardMemberAction::run(
        $program,
        'user',
        $sharerId,
        $version,
        'member-portal',
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
    );

    $fact = RewardLedgerFixtures::fact('src-multi-tier-1', $sharerId, '2026-06-12T00:00:00Z', 'attendance', [
        'actorId' => $participantId,
        'sharerId' => $sharerId,
        'isAttributed' => true,
        'verification' => RewardVerificationLevel::StaffVerified,
    ]);
    $reader = new FakeRewardSourceFactReader;
    $reader->seed($fact);
    $decision = CarbonImmutable::parse('2026-06-15T00:00:00Z');

    $candidates = RewardEvaluationCandidates::for($fact, $decision);
    $candidateMilestoneCount = array_sum(array_map(
        static fn (array $candidate): int => $candidate['role'] === RewardBeneficiaryRole::Sharer
            ? count($candidate['milestones'])
            : 0,
        $candidates,
    ));
    $candidateRoles = array_values(array_unique(array_map(
        static fn (array $candidate): string => $candidate['role']->value,
        $candidates,
    )));
    sort($candidateRoles);

    expect($candidateMilestoneCount)->toBe(4)
        ->and($candidateRoles)->toBe([
            RewardBeneficiaryRole::Participant->value,
            RewardBeneficiaryRole::Sharer->value,
        ]);

    (new EvaluateRewardEntitlementJob($fact->sourceBusinessKey))->handle(
        $reader,
        new EvaluateRewardEntitlementAction($reader),
    );

    $cycles = RewardMilestoneCycle::query()
        ->where('program_id', $program->getKey())
        ->where('recipient_id', $sharerId)
        ->get()
        ->keyBy('milestone_key');
    $milestoneAwards = RewardAward::query()
        ->whereIn('id', $cycles->pluck('award_id')->filter())
        ->orderBy('amount_snapshot->points')
        ->get();

    expect($cycles->keys()->sort()->values()->all())->toBe(['tier-2', 'tier-3', 'tier-4', 'tier-5'])
        ->and($cycles->every(fn (RewardMilestoneCycle $cycle): bool => $cycle->status === RewardMilestoneStatus::Achieved && $cycle->current_count === 1))->toBeTrue()
        ->and($milestoneAwards)->toHaveCount(4)
        ->and($milestoneAwards->pluck('amount_snapshot')->map(fn (array $amount): int => $amount['points'])->sort()->values()->all())->toBe([20, 30, 40, 50])
        ->and($milestoneAwards->every(fn (RewardAward $award): bool => $award->beneficiary_role === RewardBeneficiaryRole::Sharer && $award->status === RewardAwardStatus::Approved))->toBeTrue()
        ->and(RewardAward::query()->where('source_business_key', $fact->sourceBusinessKey)->where('beneficiary_role', RewardBeneficiaryRole::Sharer->value)->where('status', RewardAwardStatus::Rejected)->count())->toBe(0);
});

it('counts actor-bound milestone contributions from unattributed facts', function (): void {
    [$program, $recipient] = roleMilestoneProgram('role-mile-bind', 'two-checkins', 2);
    $family = $program->family_key;
    $programId = (string) $program->getKey();

    $reader = new FakeRewardSourceFactReader;
    $sharerId = (string) Str::uuid();

    $first = roleMilestoneRecord($reader, $recipient, $family, 'two-checkins', 'src-rm-1', '2026-06-10T00:00:00Z', $programId, RewardBeneficiaryRole::Participant, [
        'actorId' => $recipient,
        'sharerId' => $sharerId,
        'isAttributed' => false,
    ]);

    $second = roleMilestoneRecord($reader, $recipient, $family, 'two-checkins', 'src-rm-2', '2026-06-15T00:00:00Z', $programId, RewardBeneficiaryRole::Participant, [
        'actorId' => $recipient,
        'sharerId' => $sharerId,
        'isAttributed' => false,
    ]);

    expect($first['achieved'])->toBeFalse()
        ->and($second['achieved'])->toBeTrue();

    $award = RewardAward::query()->whereKey($second['cycle']->award_id)->firstOrFail();

    expect($award->beneficiary_role)->toBe(RewardBeneficiaryRole::Participant)
        ->and($award->status)->toBe(RewardAwardStatus::Approved);
});

it('completes a named milestone only from its configured outcome, role and verification', function (): void {
    [$program, $recipient] = roleMilestoneProgram('role-mile-configured', 'bintang-1', 2, [
        'label' => 'Bintang 1',
        'outcomes' => ['attendance'],
        'roles' => [RewardBeneficiaryRole::Participant->value],
        'min_verification' => 'staff_verified',
        'first_only' => false,
    ]);
    $reader = new FakeRewardSourceFactReader;
    $programId = (string) $program->getKey();
    $family = $program->family_key;
    $factOverrides = [
        'actorId' => $recipient,
        'sharerId' => (string) Str::uuid(),
        'verification' => RewardVerificationLevel::StaffVerified,
    ];

    $first = roleMilestoneRecord($reader, $recipient, $family, 'bintang-1', 'src-bintang-1', '2026-06-10T00:00:00Z', $programId, RewardBeneficiaryRole::Participant, $factOverrides);
    $second = roleMilestoneRecord($reader, $recipient, $family, 'bintang-1', 'src-bintang-2', '2026-06-15T00:00:00Z', $programId, RewardBeneficiaryRole::Participant, $factOverrides);

    expect($first['achieved'])->toBeFalse()
        ->and($second['achieved'])->toBeTrue()
        ->and($second['cycle']->status)->toBe(RewardMilestoneStatus::Achieved)
        ->and($second['cycle']->current_count)->toBe(2);
});

it('refuses milestone contributions that do not bind the contributor role to the recipient', function (): void {
    [$program, $recipient] = roleMilestoneProgram('role-mile-mismatch', 'two-checkins', 2);
    $family = $program->family_key;
    $programId = (string) $program->getKey();

    // Sharer-bound role on an unattributed fact: attribution stays mandatory.
    $unattributedReader = new FakeRewardSourceFactReader;

    expect(fn () => roleMilestoneRecord(
        $unattributedReader,
        $recipient,
        $family,
        'two-checkins',
        'src-rm-sharer-1',
        '2026-06-10T00:00:00Z',
        $programId,
        RewardBeneficiaryRole::Recipient,
        ['isAttributed' => false],
    ))->toThrow(InvalidArgumentException::class, 'unattributed');

    // Participant role where the recipient is only the sharer: wrong-side involvement.
    $wrongSideReader = new FakeRewardSourceFactReader;

    expect(fn () => roleMilestoneRecord(
        $wrongSideReader,
        $recipient,
        $family,
        'two-checkins',
        'src-rm-wrong-1',
        '2026-06-10T00:00:00Z',
        $programId,
        RewardBeneficiaryRole::Participant,
        ['actorId' => (string) Str::uuid()],
    ))->toThrow(InvalidArgumentException::class, 'does not bind');

    // Staff role on a non-staff actor: the actor role must match.
    $staffReader = new FakeRewardSourceFactReader;

    expect(fn () => roleMilestoneRecord(
        $staffReader,
        $recipient,
        $family,
        'two-checkins',
        'src-rm-staff-1',
        '2026-06-10T00:00:00Z',
        $programId,
        RewardBeneficiaryRole::Staff,
        ['actorId' => $recipient, 'sharerId' => (string) Str::uuid()],
    ))->toThrow(InvalidArgumentException::class, 'does not bind');

    expect(RewardAward::query()->where('program_id', $programId)->count())->toBe(0);
});

it('advances a held participant award earned from an unattributed fact', function (): void {
    [$program, , , $memberId] = RewardLedgerFixtures::seedActiveEnrolled('role-trans');

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-role-trans-1', (string) Str::uuid(), '2026-06-12T00:00:00Z', 'attendance', [
        'actorId' => $memberId,
        'isAmbiguousDevice' => true,
        'isAttributed' => false,
    ]));

    $action = new EvaluateRewardEntitlementAction($reader);
    $held = $action->handle(
        $program,
        RewardComponent::Points,
        'user',
        $memberId,
        RewardBeneficiaryRole::Participant,
        'src-role-trans-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    expect($held->outcome)->toBe(RewardEvaluationOutcome::Held)
        ->and($held->award->status)->toBe(RewardAwardStatus::Held)
        ->and($held->award->beneficiary_role)->toBe(RewardBeneficiaryRole::Participant);

    $staff = RewardLedgerFixtures::staffId();
    $approved = (new TransitionRewardAwardAction(null, $reader))->handle(
        $held->award,
        RewardAwardStatus::Approved,
        'user',
        $staff,
        'staff',
        'manual review cleared the ambiguity',
        null,
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    expect($approved->status)->toBe(RewardAwardStatus::Approved);
});
