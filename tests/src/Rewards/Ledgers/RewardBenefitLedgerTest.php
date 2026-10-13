<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\CancelRewardBenefitGrantAction;
use AIArmada\Rewards\Actions\ConsumeRewardBenefitAction;
use AIArmada\Rewards\Actions\CreateRewardBenefitItemAction;
use AIArmada\Rewards\Actions\CreditRewardPointsAction;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\ExpireRewardBenefitGrantAction;
use AIArmada\Rewards\Actions\FulfillRewardBenefitAction;
use AIArmada\Rewards\Actions\RecordRewardBenefitRecallAction;
use AIArmada\Rewards\Actions\RedeemRewardBenefitAction;
use AIArmada\Rewards\Actions\RetireRewardBenefitItemAction;
use AIArmada\Rewards\Actions\RevokeRewardBenefitGrantAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Enums\RewardBenefitItemStatus;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Support\FundedRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\FundedRewardReadinessChecker;
use AIArmada\Rewards\Support\RewardBenefitStockLedger;
use AIArmada\Rewards\Support\RewardPointLedger;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function benefitLedgerProgram(string $prefix): array
{
    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram($prefix, RewardProgramMode::Points);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $program = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    return [$program, $version];
}

function benefitLedgerFundedMember($program, $version, int $points): array
{
    $memberId = (string) Str::uuid();

    EnrollRewardMemberAction::run(
        $program,
        'user',
        $memberId,
        $version,
        'member-portal',
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
    );

    CreditRewardPointsAction::run(
        'user',
        $memberId,
        $program->family_key,
        'pts',
        $points,
        CarbonImmutable::parse('2026-06-11T00:00:00Z'),
        null,
        null,
        (string) $program->getKey(),
        'seed:benefit-credit-' . $memberId,
        'system',
        null,
        'test-seed',
        CarbonImmutable::parse('2026-06-11T00:00:00Z'),
    );

    return [$memberId];
}

function benefitLedgerBalances(string $memberId, string $family): array
{
    $buckets = RewardPointLedger::balancesFor('user', $memberId, $family, 'pts', CarbonImmutable::parse('2026-07-01T00:00:00Z'));

    return ['available' => $buckets->available, 'reserved' => $buckets->reserved, 'spent' => $buckets->spent];
}

it('creates and retires catalog items with frozen terms', function () {
    [$program] = benefitLedgerProgram('ben-cat');
    $staff = RewardLedgerFixtures::staffId();

    $item = CreateRewardBenefitItemAction::run(
        $program,
        'ebook-credit',
        'E-book credit',
        ['Good standing'],
        100,
        'pts',
        false,
        10,
        'user',
        $staff,
        'staff',
    );

    expect($item->status)->toBe(RewardBenefitItemStatus::Active)
        ->and(RewardBenefitStockLedger::balances($item)['available'])->toBe(10);

    expect(fn () => CreateRewardBenefitItemAction::run(
        $program,
        'bad-both',
        'Bad',
        ['x'],
        100,
        'pts',
        true,
        10,
        'user',
        $staff,
        'staff',
    ))->toThrow(InvalidArgumentException::class, 'either a point cost or direct entitlement');

    $retired = RetireRewardBenefitItemAction::run($item, 'user', $staff, 'staff', 'season-over');

    expect($retired->status)->toBe(RewardBenefitItemStatus::Retired)
        ->and($retired->retired_at)->not->toBeNull();
});

it('redeems, fulfills, and consumes a point-funded benefit exactly', function () {
    [$program] = benefitLedgerProgram('ben-redeem');
    [$memberId] = benefitLedgerFundedMember($program, $program->currentVersion, 500);
    [$staff, $operator] = [RewardLedgerFixtures::staffId(), RewardLedgerFixtures::staffId()];

    $item = CreateRewardBenefitItemAction::run(
        $program,
        'workshop-seat',
        'Workshop seat',
        ['Good standing'],
        200,
        'pts',
        false,
        5,
        'user',
        $staff,
        'staff',
    );

    $grant = RedeemRewardBenefitAction::run(
        'user',
        $memberId,
        $program->family_key,
        $item,
        2,
        'red-001',
        'manual',
        'user',
        $memberId,
        'member',
        null,
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    expect($grant->status)->toBe(RewardBenefitGrantStatus::Reserved)
        ->and(benefitLedgerBalances($memberId, $program->family_key))
        ->toBe(['available' => 100, 'reserved' => 400, 'spent' => 0]);

    $issued = FulfillRewardBenefitAction::run(
        $grant,
        ['handoff_reference' => 'DESK-1'],
        'user',
        $operator,
        'staff',
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    expect($issued->status)->toBe(RewardBenefitGrantStatus::Issued)
        ->and(benefitLedgerBalances($memberId, $program->family_key))
        ->toBe(['available' => 100, 'reserved' => 0, 'spent' => 400]);

    $consumed = ConsumeRewardBenefitAction::run(
        $issued,
        ['use_reference' => 'USED-1'],
        'user',
        $memberId,
        'member',
        CarbonImmutable::parse('2026-06-16T01:00:00Z'),
    );

    expect($consumed->status)->toBe(RewardBenefitGrantStatus::Consumed);
});

it('cancels a pending redemption and restores exactly once', function () {
    [$program] = benefitLedgerProgram('ben-cancel');
    [$memberId] = benefitLedgerFundedMember($program, $program->currentVersion, 300);
    $staff = RewardLedgerFixtures::staffId();

    $item = CreateRewardBenefitItemAction::run(
        $program,
        'merch-pack',
        'Merch pack',
        ['Good standing'],
        150,
        'pts',
        false,
        4,
        'user',
        $staff,
        'staff',
    );

    $grant = RedeemRewardBenefitAction::run(
        'user',
        $memberId,
        $program->family_key,
        $item,
        1,
        'red-cancel-1',
        'manual',
        'user',
        $memberId,
        'member',
        null,
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $cancelled = CancelRewardBenefitGrantAction::run(
        $grant,
        'user',
        $memberId,
        'member',
        'changed-mind',
        CarbonImmutable::parse('2026-06-15T01:00:00Z'),
    );

    expect($cancelled->status)->toBe(RewardBenefitGrantStatus::Revoked)
        ->and(benefitLedgerBalances($memberId, $program->family_key))
        ->toBe(['available' => 300, 'reserved' => 0, 'spent' => 0])
        ->and(RewardBenefitStockLedger::balances($item->refresh())['available'])->toBe(4);
});

it('revokes an unused issued grant and refuses revocation after use', function () {
    [$program] = benefitLedgerProgram('ben-revoke');
    [$memberId] = benefitLedgerFundedMember($program, $program->currentVersion, 300);
    [$staff, $operator] = [RewardLedgerFixtures::staffId(), RewardLedgerFixtures::staffId()];

    $item = CreateRewardBenefitItemAction::run(
        $program,
        'course-seat',
        'Course seat',
        ['Good standing'],
        150,
        'pts',
        false,
        4,
        'user',
        $staff,
        'staff',
    );

    $grant = RedeemRewardBenefitAction::run(
        'user',
        $memberId,
        $program->family_key,
        $item,
        1,
        'red-revoke-1',
        'manual',
        'user',
        $memberId,
        'member',
        null,
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $issued = FulfillRewardBenefitAction::run(
        $grant,
        ['handoff_reference' => 'DESK-7'],
        'user',
        $operator,
        'staff',
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    expect(fn () => RevokeRewardBenefitGrantAction::run(
        $issued,
        'user',
        $operator,
        'staff',
        'recall',
        CarbonImmutable::parse('2026-06-16T01:00:00Z'),
    ))->toThrow(InvalidArgumentException::class, 'recall_reference');

    RecordRewardBenefitRecallAction::run(
        $issued,
        'RECALL-1',
        'user',
        $operator,
        'staff',
        CarbonImmutable::parse('2026-06-16T01:30:00Z'),
    );

    $revoked = RevokeRewardBenefitGrantAction::run(
        $issued->refresh(),
        'user',
        $operator,
        'staff',
        'seat-recalled',
        CarbonImmutable::parse('2026-06-16T02:00:00Z'),
    );

    expect($revoked->status)->toBe(RewardBenefitGrantStatus::Revoked)
        ->and(benefitLedgerBalances($memberId, $program->family_key)['available'])->toBe(300)
        ->and(RewardBenefitStockLedger::balances($item->refresh())['available'])->toBe(4);

    $grant2 = RedeemRewardBenefitAction::run(
        'user',
        $memberId,
        $program->family_key,
        $item,
        1,
        'red-revoke-2',
        'manual',
        'user',
        $memberId,
        'member',
        null,
        CarbonImmutable::parse('2026-06-17T00:00:00Z'),
    );
    $issued2 = FulfillRewardBenefitAction::run(
        $grant2,
        ['handoff_reference' => 'DESK-8'],
        'user',
        $operator,
        'staff',
        CarbonImmutable::parse('2026-06-17T01:00:00Z'),
    );
    ConsumeRewardBenefitAction::run(
        $issued2,
        ['use_reference' => 'USED'],
        'user',
        $memberId,
        'member',
        CarbonImmutable::parse('2026-06-17T02:00:00Z'),
    );

    expect(fn () => RevokeRewardBenefitGrantAction::run(
        $issued2->refresh(),
        'user',
        $operator,
        'staff',
        'too-late',
        CarbonImmutable::parse('2026-06-17T03:00:00Z'),
    ))->toThrow(InvalidArgumentException::class, 'Only issued grants can be revoked');
});

it('expires reserved grants past the catalog expiry and restores points and stock', function () {
    [$program] = benefitLedgerProgram('ben-expire');
    [$memberId] = benefitLedgerFundedMember($program, $program->currentVersion, 300);
    $staff = RewardLedgerFixtures::staffId();

    $item = CreateRewardBenefitItemAction::run(
        $program,
        'season-pass',
        'Season pass',
        ['Good standing'],
        150,
        'pts',
        false,
        4,
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    $grant = RedeemRewardBenefitAction::run(
        'user',
        $memberId,
        $program->family_key,
        $item,
        1,
        'red-expire-1',
        'manual',
        'user',
        $memberId,
        'member',
        null,
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    expect(fn () => ExpireRewardBenefitGrantAction::run(
        $grant,
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-15T12:00:00Z'),
    ))->toThrow(InvalidArgumentException::class, 'passed catalog expiry');

    $expired = ExpireRewardBenefitGrantAction::run(
        $grant,
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-17T00:00:00Z'),
    );

    expect($expired->status)->toBe(RewardBenefitGrantStatus::Expired)
        ->and(benefitLedgerBalances($memberId, $program->family_key))
        ->toBe(['available' => 300, 'reserved' => 0, 'spent' => 0])
        ->and(RewardBenefitStockLedger::balances($item->refresh())['available'])->toBe(4);
});

it('fulfills a direct-entitlement benefit award through the catalog', function () {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram('ben-direct', RewardProgramMode::Benefit);
    $staff = RewardLedgerFixtures::staffId();

    expect(fn () => TransitionRewardProgramStatusAction::run(
        TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready),
        RewardProgramStatus::Active,
    ))->toThrow(InvalidArgumentException::class, 'benefit-catalog-unavailable');

    CreateRewardBenefitItemAction::run(
        $program,
        'welcome-credit',
        'Welcome credit',
        ['Member account in good standing', 'One redemption per award'],
        null,
        null,
        true,
        5,
        'user',
        $staff,
        'staff',
    );

    $program = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    $memberId = (string) Str::uuid();

    EnrollRewardMemberAction::run(
        $program,
        'user',
        $memberId,
        $version,
        'member-portal',
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
    );

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-ben-direct-1', $memberId));

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Benefit,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-ben-direct-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $transition = new TransitionRewardAwardAction(app(RewardFinancialReadinessChecker::class), $reader);

    $approved = $transition->handle(
        $result->award,
        RewardAwardStatus::Approved,
        'user',
        $staff,
        'staff',
        'verified-ok',
        null,
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    $fulfilled = $transition->handle(
        $approved,
        RewardAwardStatus::Fulfilled,
        'user',
        $staff,
        'staff',
        'grant-issued',
        null,
        null,
        CarbonImmutable::parse('2026-06-16T01:00:00Z'),
    );

    expect($fulfilled->status)->toBe(RewardAwardStatus::Fulfilled);
});
