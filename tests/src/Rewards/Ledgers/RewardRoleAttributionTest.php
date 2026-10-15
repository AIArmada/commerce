<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\RecordRewardMilestoneContributionAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardEvaluationOutcome;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Support\RewardEvaluationCandidates;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function roleMilestoneProgram(string $prefix, string $key, int $target): array
{
    [$program, $version, $enrollment, $memberId] = RewardLedgerFixtures::seedActiveEnrolled(
        $prefix,
        RewardProgramMode::Points,
        ['milestones' => [['key' => $key, 'target_count' => $target, 'window_days' => 30, 'reward' => ['type' => 'points', 'points' => 25, 'unit' => 'pts']]]],
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
