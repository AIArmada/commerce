<?php

declare(strict_types=1);

use AIArmada\Rewards\Actions\ApproveRewardPayoutAction;
use AIArmada\Rewards\Actions\CancelRewardPayoutAction;
use AIArmada\Rewards\Actions\ConfirmRewardPayoutAction;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\ReconcileRewardPayoutAction;
use AIArmada\Rewards\Actions\RecordRewardFundingAction;
use AIArmada\Rewards\Actions\RecordRewardFundingSourceAction;
use AIArmada\Rewards\Actions\RegisterRewardPayeeAction;
use AIArmada\Rewards\Actions\RequestRewardPayoutAction;
use AIArmada\Rewards\Actions\SubmitRewardPayoutAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Actions\VerifyRewardPayeeAction;
use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardBudgetMovementKind;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Models\RewardBudgetMovement;
use AIArmada\Rewards\Models\RewardPayee;
use AIArmada\Rewards\Support\FundedRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\RewardBudgetLedger;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use AIArmada\Rewards\Tests\Ledgers\Support\ScriptedPayoutExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function payoutHarness(string $prefix): array
{
    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram($prefix, RewardProgramMode::Cash);
    $program = RewardLedgerFixtures::forceActive($program);

    $memberId = (string) Str::uuid();
    $staff = RewardLedgerFixtures::staffId();
    $enrolledAt = CarbonImmutable::parse('2026-06-10T00:00:00Z');

    EnrollRewardMemberAction::run($program, 'user', $memberId, $version, 'member-portal', $enrolledAt);
    RecordRewardFundingSourceAction::run('src-' . $prefix, 'MYR', 100000, 'user', $staff, 'staff');
    RecordRewardFundingAction::run($program, 'MYR', 100000, 'src-' . $prefix, 'user', $staff, 'staff');

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-' . $prefix . '-1', $memberId));

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Cash,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-' . $prefix . '-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $award = $result->award->refresh();

    $payee = RegisterRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'acct-' . $prefix,
        'Manual handoff',
        'user',
        $memberId,
        'member',
    );
    VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'acct-' . $prefix,
        'Manual handoff',
        'user',
        $staff,
        'staff',
        ['verified_from_registration_id' => (string) $payee->getKey()],
    );

    (new TransitionRewardAwardAction(app(FundedRewardFinancialReadinessChecker::class), $reader))->handle(
        $award,
        RewardAwardStatus::Approved,
        'user',
        $staff,
        'staff',
        'verified-ok',
        null,
        'op:' . $prefix . ':approve',
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    return [$program->refresh(), $award->refresh(), $memberId, $staff];
}

it('submits manual payouts as submitted and confirms them with spend', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-core-1');

    $request = RequestRewardPayoutAction::run(
        $award,
        RewardPayee::query()->where('recipient_id', $memberId)->where('status', 'verified')->firstOrFail(),
        'manual',
        'program',
        'user',
        $staff,
        'staff',
    );

    expect($request->status)->toBe(RewardPayoutRequestStatus::Requested)
        ->and((int) $request->fee_minor)->toBe(0);

    ApproveRewardPayoutAction::run($request, 'user', (string) Str::uuid(), 'staff', 'looks-good');

    $submitted = SubmitRewardPayoutAction::run($request, 'user', $staff, 'staff');

    expect($submitted->status)->toBe(RewardPayoutRequestStatus::Submitted)
        ->and($submitted->external_provider)->toBe('manual')
        ->and($submitted->external_payout_id)->toStartWith('manual:');

    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe((int) $request->amount_minor);

    $confirmed = ConfirmRewardPayoutAction::run(
        $request,
        ['bank_reference' => 'bank-ref-1', 'transferred_at' => '2026-06-17T00:00:00Z'],
        'user',
        (string) Str::uuid(),
        'staff',
    );

    expect($confirmed->status)->toBe(RewardPayoutRequestStatus::Confirmed);

    expect(RewardBudgetMovement::query()->where('program_id', $program->getKey())
        ->where('kind', RewardBudgetMovementKind::Spend)->sum('amount_minor'))->toBe((int) $request->amount_minor)
        ->and(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe(0);
});

it('cancels requested payouts directly with release', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-core-2');

    $request = RequestRewardPayoutAction::run(
        $award,
        RewardPayee::query()->where('recipient_id', $memberId)->where('status', 'verified')->firstOrFail(),
        'manual',
        'program',
        'user',
        $staff,
        'staff',
    );

    $cancelled = CancelRewardPayoutAction::run($request, 'user', $staff, 'staff', 'recipient-asked');

    expect($cancelled->status)->toBe(RewardPayoutRequestStatus::Cancelled)
        ->and(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe(0);
});

it('reconciles submitted manual payouts as evidence-only', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-core-3');

    $request = RequestRewardPayoutAction::run(
        $award,
        RewardPayee::query()->where('recipient_id', $memberId)->where('status', 'verified')->firstOrFail(),
        'manual',
        'program',
        'user',
        $staff,
        'staff',
    );
    ApproveRewardPayoutAction::run($request, 'user', (string) Str::uuid(), 'staff', 'ok');
    SubmitRewardPayoutAction::run($request, 'user', $staff, 'staff');

    $reconciled = ReconcileRewardPayoutAction::run($request, 'user', $staff, 'staff');

    expect($reconciled->status)->toBe(RewardPayoutRequestStatus::Submitted)
        ->and($reconciled->evidence['last_reconciled_at'] ?? null)->not->toBeNull()
        ->and(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe((int) $request->amount_minor);
});

it('maps executor outcomes honestly without duplicating money', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-core-4');

    app()->bind(RewardPayoutExecutor::class, fn (): RewardPayoutExecutor => new ScriptedPayoutExecutor([
        new RewardPayoutExecution(status: 'completed', externalPayoutId: 'op-1', externalProvider: 'scripted', externalReference: 'REF-1'),
    ]));

    $request = RequestRewardPayoutAction::run(
        $award,
        RewardPayee::query()->where('recipient_id', $memberId)->where('status', 'verified')->firstOrFail(),
        'manual',
        'program',
        'user',
        $staff,
        'staff',
    );
    ApproveRewardPayoutAction::run($request, 'user', (string) Str::uuid(), 'staff', 'ok');

    $confirmed = SubmitRewardPayoutAction::run($request, 'user', $staff, 'staff');

    expect($confirmed->status)->toBe(RewardPayoutRequestStatus::Confirmed)
        ->and($confirmed->provider_reference)->toBe('REF-1')
        ->and(RewardBudgetMovement::query()->where('program_id', $program->getKey())
            ->where('kind', RewardBudgetMovementKind::Spend)->sum('amount_minor'))->toBe((int) $request->amount_minor);

    expect(fn () => SubmitRewardPayoutAction::run($request->refresh(), 'user', $staff, 'staff'))
        ->toThrow(InvalidArgumentException::class, 'approved');
});
