<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\ApproveRewardPayoutAction;
use AIArmada\Rewards\Actions\ConfirmRewardPayoutAction;
use AIArmada\Rewards\Actions\CreditRewardPointsAction;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\OpenRewardRecoveryAction;
use AIArmada\Rewards\Actions\RecordRewardFundingAction;
use AIArmada\Rewards\Actions\RecordRewardFundingSourceAction;
use AIArmada\Rewards\Actions\RegisterRewardPayeeAction;
use AIArmada\Rewards\Actions\RejectRewardPayeeAction;
use AIArmada\Rewards\Actions\RequestRewardPayoutAction;
use AIArmada\Rewards\Actions\ReverseRewardFulfillmentAction;
use AIArmada\Rewards\Actions\SettleRewardRecoveryAction;
use AIArmada\Rewards\Actions\SubmitRewardPayoutAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Actions\VerifyRewardPayeeAction;
use AIArmada\Rewards\Actions\WriteOffRewardRecoveryAction;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardPayeeStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Enums\RewardRecoveryStatus;
use AIArmada\Rewards\Jobs\EvaluateRewardEntitlementJob;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use AIArmada\Rewards\Models\RewardPointLot;
use AIArmada\Rewards\Models\RewardRecovery;
use AIArmada\Rewards\Support\FundedRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\FundedRewardReadinessChecker;
use AIArmada\Rewards\Support\NullRewardPayoutExecutor;
use AIArmada\Rewards\Support\PointLedgerRewardReservationResolver;
use AIArmada\Rewards\Support\RewardBudgetLedger;
use AIArmada\Rewards\Support\RewardPointLedger;
use AIArmada\Rewards\Support\RewardPublishAssembler;
use AIArmada\Rewards\Support\RewardRuleValidator;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use AIArmada\Rewards\Tests\Ledgers\Support\ScriptedPayoutExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

function financeLedgerProgram(string $prefix, int $fundedMinor = 10000): array
{
    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram($prefix, RewardProgramMode::Cash);
    $funder = RewardLedgerFixtures::staffId();

    RecordRewardFundingSourceAction::run(
        'TREASURY-' . $prefix,
        'MYR',
        $fundedMinor,
        'user',
        $funder,
        'staff',
        ['document' => 'treasury-receipt-1'],
        CarbonImmutable::parse('2026-06-02T00:00:00Z'),
    );

    RecordRewardFundingAction::run(
        $program,
        'MYR',
        $fundedMinor,
        'TREASURY-' . $prefix,
        'user',
        $funder,
        'staff',
        ['document' => 'treasury-receipt-1'],
        CarbonImmutable::parse('2026-06-02T00:00:00Z'),
    );

    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $program = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    return [$program, $version];
}

it('rejects pending payee registrations and never touches verified rows', function (): void {
    $memberId = (string) Str::uuid();
    $staffId = RewardLedgerFixtures::staffId();

    $pending = RegisterRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'op-ref-9',
        'Desk 9',
        'user',
        $memberId,
        'member',
    );

    $rejected = RejectRewardPayeeAction::run(
        $pending,
        'user',
        $staffId,
        'staff',
        'Account holder name mismatch.',
    );

    expect($rejected->status)->toBe(RewardPayeeStatus::Rejected)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($rejected->evidence['rejection_reason'] ?? null)->toBe('Account holder name mismatch.');

    $verified = VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'op-ref-9',
        'Desk 9',
        'user',
        $staffId,
        'staff',
    );

    expect(fn () => RejectRewardPayeeAction::run($verified, 'user', $staffId, 'staff', 'Too late.'))
        ->toThrow(InvalidArgumentException::class, 'Only a pending payee registration can be rejected.');
});

it('reverses a confirmed cash payout through recovery and settles the budget', function (): void {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);
    app()->bind(RewardPayoutExecutor::class, fn (): RewardPayoutExecutor => new ScriptedPayoutExecutor([
        new RewardPayoutExecution(status: 'pending', externalPayoutId: 'op-cashrec', externalReferenceId: 'backing-cashrec', externalProvider: 'scripted', externalReference: 'REF-1'),
    ]));

    [$program, $version] = financeLedgerProgram('fin-cashrec');
    $staff = RewardLedgerFixtures::staffId();
    $verifier = RewardLedgerFixtures::staffId();
    $requester = RewardLedgerFixtures::staffId();
    $approver = RewardLedgerFixtures::staffId();
    $confirmer = RewardLedgerFixtures::staffId();
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
    $reader->seed(RewardLedgerFixtures::fact('src-fin-cashrec-1', $memberId));

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Cash,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-fin-cashrec-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $payee = VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'ACCT-R',
        'Desk R',
        'user',
        $verifier,
        'staff',
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    $transition = new TransitionRewardAwardAction(app(RewardFinancialReadinessChecker::class), $reader);

    $approved = $transition->handle(
        $result->award,
        RewardAwardStatus::Approved,
        'user',
        $staff,
        'staff',
        'policy-ok',
        null,
        null,
        CarbonImmutable::parse('2026-06-17T00:00:00Z'),
    );

    $request = RequestRewardPayoutAction::run(
        $approved,
        $payee,
        'manual',
        'program',
        'user',
        $requester,
        'staff',
        null,
        CarbonImmutable::parse('2026-06-17T00:00:00Z'),
    );
    $request = ApproveRewardPayoutAction::run(
        $request,
        'user',
        $approver,
        'staff',
        'finance-ok',
        CarbonImmutable::parse('2026-06-17T01:00:00Z'),
    );
    $request = SubmitRewardPayoutAction::run(
        $request,
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-18T00:00:00Z'),
    );

    expect(fn () => $transition->handle(
        $approved,
        RewardAwardStatus::Fulfilled,
        'user',
        $staff,
        'staff',
        'paid-pending-confirm',
        null,
        null,
        CarbonImmutable::parse('2026-06-18T01:00:00Z'),
    ))->toThrow(InvalidArgumentException::class, 'payout-not-confirmed:submitted');

    $request = ConfirmRewardPayoutAction::run(
        $request,
        ['bank_reference' => 'BANK-R'],
        'user',
        $confirmer,
        'staff',
        CarbonImmutable::parse('2026-06-19T00:00:00Z'),
    );

    $fulfilled = $transition->handle(
        $approved,
        RewardAwardStatus::Fulfilled,
        'user',
        $staff,
        'staff',
        'paid',
        null,
        null,
        CarbonImmutable::parse('2026-06-19T01:00:00Z'),
    );

    $outcome = ReverseRewardFulfillmentAction::run(
        $fulfilled,
        'user',
        $staff,
        'staff',
        'source-invalid',
        null,
        CarbonImmutable::parse('2026-06-20T00:00:00Z'),
    );

    expect($outcome['award']->status)->toBe(RewardAwardStatus::Reversed)
        ->and($outcome['recovery_ids'])->toHaveCount(1);

    $recovery = RewardRecovery::query()->whereKey($outcome['recovery_ids'][0])->firstOrFail();

    expect($recovery->status)->toBe(RewardRecoveryStatus::Open)
        ->and($recovery->amount_minor)->toBe(100);

    expect(fn () => SettleRewardRecoveryAction::run(
        $recovery,
        [],
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-21T00:00:00Z'),
    ))->toThrow(InvalidArgumentException::class, 'recovery_proof');

    $settled = SettleRewardRecoveryAction::run(
        $recovery,
        ['recovery_proof' => 'BANK-RETURN-1'],
        'user',
        $staff,
        'staff',
        CarbonImmutable::parse('2026-06-21T00:00:00Z'),
    );

    expect($settled->status)->toBe(RewardRecoveryStatus::Settled)
        ->and(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR'))
        ->toBe(['funded' => 10000, 'reserved' => 0, 'net_spent' => 0, 'available' => 10000]);

    $written = WriteOffRewardRecoveryAction::run(
        OpenRewardRecoveryAction::run(
            $fulfilled->refresh(),
            'dispute',
            null,
            null,
            null,
            null,
            50,
            'pts',
            'user',
            $staff,
            'staff',
            'small-loss',
            null,
            CarbonImmutable::parse('2026-06-22T00:00:00Z'),
        ),
        'user',
        $staff,
        'staff',
        'unrecoverable',
        CarbonImmutable::parse('2026-06-23T00:00:00Z'),
    );

    expect($written->status)->toBe(RewardRecoveryStatus::WrittenOff);
});

it('expires due point lots through the operator command, idempotently', function (): void {
    [$program] = RewardLedgerFixtures::seedVersionedProgram('expiry-proof', RewardProgramMode::Points);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $program = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    $memberId = (string) Str::uuid();

    CreditRewardPointsAction::run(
        'user',
        $memberId,
        $program->family_key,
        'pts',
        100,
        CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
        null,
        (string) $program->getKey(),
        'seed:expiry-' . $memberId,
        'system',
        null,
        'test-seed',
        CarbonImmutable::parse('2026-06-01T00:00:00Z'),
    );

    $exit = Artisan::call('rewards:expire-point-lots', ['--as-of' => '2026-06-16T00:00:00Z']);

    expect($exit)->toBe(0)
        ->and(RewardPointLot::query()->where('recipient_id', $memberId)->whereNotNull('expired_at')->count())->toBe(1);

    $buckets = RewardPointLedger::balancesFor('user', $memberId, (string) $program->family_key, 'pts', CarbonImmutable::parse('2026-06-16T00:00:00Z'));
    expect($buckets->available)->toBe(0);

    $again = Artisan::call('rewards:expire-point-lots', ['--as-of' => '2026-06-16T00:00:00Z']);

    expect($again)->toBe(0)
        ->and(Artisan::output())->toContain('0 lot movements');

    CreditRewardPointsAction::run(
        'user',
        $memberId,
        $program->family_key,
        'pts',
        50,
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
        CarbonImmutable::parse('2026-12-01T00:00:00Z'),
        null,
        (string) $program->getKey(),
        'seed:expiry-fresh-' . $memberId,
        'system',
        null,
        'test-seed',
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
    );

    Artisan::call('rewards:expire-point-lots', ['--as-of' => '2026-06-16T00:00:00Z']);

    $kept = RewardPointLedger::balancesFor('user', $memberId, (string) $program->family_key, 'pts', CarbonImmutable::parse('2026-06-16T00:00:00Z'));
    expect($kept->available)->toBe(50);
});

it('refuses an unparseable expiry timestamp', function (): void {
    expect(Artisan::call('rewards:expire-point-lots', ['--as-of' => 'not-a-date']))->toBe(2);
});

it('fails closed when no payout executor is bound', function (): void {
    $request = new RewardPayoutRequest;
    $executor = new NullRewardPayoutExecutor;

    expect(fn () => $executor->submit($request))->toThrow(InvalidArgumentException::class, 'no RewardPayoutExecutor is bound')
        ->and(fn () => $executor->cancel($request))->toThrow(InvalidArgumentException::class, 'no RewardPayoutExecutor is bound')
        ->and(fn () => $executor->status($request))->toThrow(InvalidArgumentException::class, 'no RewardPayoutExecutor is bound')
        ->and(fn () => $executor->settle($request, 'confirmed'))->toThrow(InvalidArgumentException::class, 'no RewardPayoutExecutor is bound');
});

it('evaluates queued business keys through the entitlement job', function (): void {
    [$program, $version, $enrollment, $memberId] = RewardLedgerFixtures::seedActiveEnrolled('fin-job');

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-fin-job-1', $memberId));

    (new EvaluateRewardEntitlementJob('src-fin-job-1'))->handle(
        $reader,
        new EvaluateRewardEntitlementAction($reader),
    );

    expect(RewardAward::query()->where('source_business_key', 'src-fin-job-1')->count())->toBe(1);

    (new EvaluateRewardEntitlementJob('src-fin-job-unknown'))->handle(
        $reader,
        new EvaluateRewardEntitlementAction($reader),
    );

    expect(RewardAward::query()->where('source_business_key', 'src-fin-job-unknown')->count())->toBe(0);
});

it('assembles publish payloads from structured controls without hand-written JSON', function (): void {
    $payload = RewardPublishAssembler::assemble(RewardProgramMode::Points, [
        'eligibility_outcomes' => ['contribution', 'attendance'],
        'eligibility_categories' => '',
        'min_verification' => 'accepted',
        'first_only' => false,
        'exclude_self_credit' => true,
        'require_enrollment' => true,
        'require_claimed_identity' => true,
        'preclaim_lookback_days' => 30,
        'hold_days' => 0,
        'cap_scope_1' => 'recipient_source_component',
        'cap_limit_1' => 1,
        'ambiguous_device' => true,
        'manual_review_outcomes' => [],
        'terms_label' => 'Member terms',
        'terms_version_label' => 'v1',
        'terms_body' => 'Earn only on verified eligible outcomes.',
        'terms_earn_basis' => 'Verified eligible outcomes only.',
        'terms_changes' => 'Changes apply prospectively after reacceptance.',
        'terms_no_ranking' => 'No public ranking.',
        'points_amount' => 10,
        'points_unit' => 'pts',
    ]);

    expect((new RewardRuleValidator)->errors($payload['rules'], RewardProgramMode::Points, 'fin-assemble'))->toBe([])
        ->and((new RewardRuleValidator)->termsErrors($payload['terms'], $payload['rules']))->toBe([])
        ->and($payload['rules']['components'][0]['points'])->toBe(10);
});

it('voids only failed-and-resolved point reservations', function (): void {
    $resolver = new PointLedgerRewardReservationResolver;

    expect($resolver->mayVoidReservation(''))->toBeFalse()
        ->and($resolver->mayVoidReservation('res-never-seen'))->toBeTrue();
});

it('freezes recovery economics at opening time', function (): void {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    [$program, $version] = financeLedgerProgram('fin-frozen');
    $staff = RewardLedgerFixtures::staffId();
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
    $reader->seed(RewardLedgerFixtures::fact('src-fin-frozen-1', $memberId));

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Cash,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-fin-frozen-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $recovery = OpenRewardRecoveryAction::run(
        $result->award,
        'dispute',
        null,
        null,
        null,
        null,
        100,
        'MYR',
        'user',
        $staff,
        'staff',
        'duplicate-send',
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    expect(fn () => $recovery->forceFill(['amount_minor' => 1])->save())
        ->toThrow(LogicException::class, 'frozen at opening time');
});
