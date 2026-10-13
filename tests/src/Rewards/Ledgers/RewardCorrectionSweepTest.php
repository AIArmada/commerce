<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\ApplyRewardSourceCorrectionsAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\InvalidateRewardMilestoneContributionAction;
use AIArmada\Rewards\Actions\RecordRewardMilestoneContributionAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardSourceCorrectionStatus;
use AIArmada\Rewards\Jobs\RecheckRewardAwardsJob;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;

function sweepHarness(string $prefix): array
{
    [$program, , , $memberId] = RewardLedgerFixtures::seedActiveEnrolled(
        $prefix,
        RewardProgramMode::Points,
        ['milestones' => [['key' => 'two', 'target_count' => 2, 'window_days' => 30, 'reward' => ['type' => 'points', 'points' => 25, 'unit' => 'pts']]]],
    );

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-' . $prefix . '-1', $memberId, '2026-06-12T00:00:00Z', 'attendance', ['isAmbiguousDevice' => true]));
    $reader->seed(RewardLedgerFixtures::fact('src-' . $prefix . '-2', $memberId));

    $evaluate = new EvaluateRewardEntitlementAction($reader);
    $staff = RewardLedgerFixtures::staffId();

    $held = $evaluate->handle(
        $program,
        RewardComponent::Points,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-' . $prefix . '-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    )->award->refresh();

    $approved = $evaluate->handle(
        $program,
        RewardComponent::Points,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-' . $prefix . '-2',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    )->award->refresh();

    (new TransitionRewardAwardAction(null, $reader))->handle(
        $approved,
        RewardAwardStatus::Approved,
        'user',
        $staff,
        'staff',
        'verified-ok',
        null,
        'op:' . $prefix . ':approve',
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    (new RecordRewardMilestoneContributionAction($reader))->handle(
        'user',
        $memberId,
        $program->family_key,
        'two',
        2,
        30,
        RewardComponent::Points,
        ['points' => 25, 'unit' => 'pts'],
        'src-' . $prefix . '-2',
        'user',
        $memberId,
        RewardBeneficiaryRole::Participant,
        'attendance',
        CarbonImmutable::parse('2026-06-12T00:00:00Z'),
        (string) $program->getKey(),
    );

    return [$program->refresh(), $held->refresh(), $approved->refresh(), $memberId, $staff];
}

it('reverses tainted keys explicitly with a full report', function (): void {
    [$program, $held, $approved, $memberId, $staff] = sweepHarness('sweep-1');

    $report = ApplyRewardSourceCorrectionsAction::run(
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-20T00:00:00Z'),
        200,
        [
            ['business_key' => 'src-sweep-1-1', 'reason' => 'source-correction', 'evidence' => ['business_key' => 'src-sweep-1-1']],
            ['business_key' => 'src-sweep-1-2', 'reason' => 'confirmed-fraud', 'evidence' => ['business_key' => 'src-sweep-1-2']],
        ],
    );

    expect($report['rejected'])->toContain((string) $held->getKey())
        ->and($report['reversed'])->toContain((string) $approved->getKey())
        ->and($report['contributions'])->toBe(1)
        ->and($report['errors'])->toBe([]);

    expect($held->refresh()->status)->toBe(RewardAwardStatus::Rejected)
        ->and($approved->refresh()->status)->toBe(RewardAwardStatus::Reversed)
        ->and(RewardMilestoneContribution::query()->where('source_business_key', 'src-sweep-1-2')->whereNotNull('revoked_at')->count())->toBe(1);

    // Re-runs converge: settled work skips.
    $rerun = ApplyRewardSourceCorrectionsAction::run(
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-20T00:00:00Z'),
        200,
        [['business_key' => 'src-sweep-1-1', 'reason' => 'source-correction', 'evidence' => []]],
    );

    expect($rerun['reversed'])->toBe([])
        ->and($rerun['rejected'])->toBe([]);
});

it('sweeps explicit keys through the command and no-ops on an empty source', function (): void {
    [$program, $held, $approved, $memberId, $staff] = sweepHarness('sweep-2');

    $this->artisan('rewards:apply-source-corrections', [
        '--actor' => $staff,
        '--actor-role' => 'staff',
        '--keys' => 'src-sweep-2-1,src-sweep-2-2',
    ])->assertSuccessful();

    expect($held->refresh()->status)->toBe(RewardAwardStatus::Rejected)
        ->and($approved->refresh()->status)->toBe(RewardAwardStatus::Reversed);

    $this->artisan('rewards:apply-source-corrections', [
        '--actor' => $staff,
        '--actor-role' => 'staff',
    ])->assertSuccessful();
});

it('rechecks awards through the fact reader when a correction lands', function (): void {
    [$program, , , $memberId] = RewardLedgerFixtures::seedActiveEnrolled('sweep-3');

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-sweep-3-1', $memberId));

    $award = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Points,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-sweep-3-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    )->award->refresh();

    expect($award->status)->toBe(RewardAwardStatus::Approved);

    // Current fact: nothing to do.
    (new RecheckRewardAwardsJob('src-sweep-3-1'))->handle(
        $reader,
        new TransitionRewardAwardAction,
        app(InvalidateRewardMilestoneContributionAction::class),
    );

    expect($award->refresh()->status)->toBe(RewardAwardStatus::Approved);

    // Corrected fact: the approved award reverses.
    $reader->seed(RewardLedgerFixtures::fact(
        'src-sweep-3-1',
        $memberId,
        '2026-06-12T00:00:00Z',
        'attendance',
        ['correctionStatus' => RewardSourceCorrectionStatus::Corrected, 'correctionId' => 'corr-1'],
    ));

    (new RecheckRewardAwardsJob('src-sweep-3-1'))->handle(
        $reader,
        new TransitionRewardAwardAction,
        app(InvalidateRewardMilestoneContributionAction::class),
    );

    expect($award->refresh()->status)->toBe(RewardAwardStatus::Reversed);
    expect(RewardAward::query()->whereKey($award->getKey())->firstOrFail()->entries()->count())->toBeGreaterThan(0);
});

it('rechecks through the key source when the key has no readable fact', function (): void {
    [$program, , , $memberId] = RewardLedgerFixtures::seedActiveEnrolled('sweep-4');

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-sweep-4-1', $memberId));

    $award = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Points,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-sweep-4-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    )->award->refresh();

    expect($award->status)->toBe(RewardAwardStatus::Approved);

    $keys = new class implements RewardCorrectionKeySource
    {
        public function taintedKeys(int $limit): array
        {
            return [];
        }

        public function causeFor(string $businessKey): ?array
        {
            return $businessKey === 'src-sweep-4-1'
                ? ['reason' => 'source-correction:withdrawn', 'evidence' => ['business_key' => $businessKey]]
                : null;
        }
    };

    // No readable fact and no taint: nothing to do.
    (new RecheckRewardAwardsJob('src-sweep-4-unknown'))->handle(
        $reader,
        new TransitionRewardAwardAction,
        app(InvalidateRewardMilestoneContributionAction::class),
        $keys,
    );

    expect($award->refresh()->status)->toBe(RewardAwardStatus::Approved);

    // No readable fact but the key source reports taint: the award reverses.
    $unseeded = new FakeRewardSourceFactReader;

    (new RecheckRewardAwardsJob('src-sweep-4-1'))->handle(
        $unseeded,
        new TransitionRewardAwardAction,
        app(InvalidateRewardMilestoneContributionAction::class),
        $keys,
    );

    expect($award->refresh()->status)->toBe(RewardAwardStatus::Reversed);
});
