<?php

declare(strict_types=1);

use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBudgetMovementKind;
use AIArmada\Rewards\Enums\RewardPayeeStatus;
use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Features\Enrollment\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Features\Entitlements\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Features\Entitlements\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Features\Finance\Actions\ApproveRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Actions\CancelRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Actions\ConfirmRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Actions\ReconcileRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Actions\RecordRewardFundingAction;
use AIArmada\Rewards\Features\Finance\Actions\RecordRewardFundingSourceAction;
use AIArmada\Rewards\Features\Finance\Actions\RegisterRewardPayeeAction;
use AIArmada\Rewards\Features\Finance\Actions\RequestRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Actions\SubmitRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Actions\VerifyRewardPayeeAction;
use AIArmada\Rewards\Features\Finance\Api\RewardPayeeSelectionApi;
use AIArmada\Rewards\Features\Finance\Models\RewardBudgetMovement;
use AIArmada\Rewards\Features\Finance\Models\RewardPayee;
use AIArmada\Rewards\Features\Finance\Persistence\RewardBudgetLedger;
use AIArmada\Rewards\Features\Finance\Queries\FundedRewardFinancialReadinessChecker;
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

    EnrollRewardMemberAction::run((string) $program->getKey(), 'user', $memberId, (string) $version->getKey(), 'member-portal', $enrolledAt);
    RecordRewardFundingSourceAction::run('src-' . $prefix, 'MYR', 100000, 'user', $staff, 'staff');
    RecordRewardFundingAction::run((string) $program->getKey(), 'MYR', 100000, 'src-' . $prefix, 'user', $staff, 'staff');

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-' . $prefix . '-1', $memberId));

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        (string) $program->getKey(),
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
        (string) $award->getKey(),
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

it('refuses a payout request replay with changed frozen fee responsibility', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-replay-terms');
    $awardId = (string) $award->getKey();
    $payee = RewardPayee::query()->where('recipient_id', $memberId)->where('status', 'verified')->firstOrFail();

    $first = RequestRewardPayoutAction::run($awardId, $payee, 'manual', 'program', 'user', $staff, 'staff');
    $replay = RequestRewardPayoutAction::run($awardId, $payee, 'manual', 'program', 'user', $staff, 'staff');

    expect($replay->getKey())->toBe($first->getKey());

    expect(fn () => RequestRewardPayoutAction::run($awardId, $payee, 'manual', 'recipient', 'user', $staff, 'staff'))
        ->toThrow(InvalidArgumentException::class, 'different frozen terms');

    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe((int) $first->amount_minor);
});

it('cancels requested payouts directly with release', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-core-2');

    $request = RequestRewardPayoutAction::run(
        (string) $award->getKey(),
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
        (string) $award->getKey(),
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
        (string) $award->getKey(),
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

it('refuses payee registration and verification for unconfigured methods', function (): void {
    config()->set('rewards.payouts.methods', ['manual']);

    $memberId = (string) Str::uuid();
    $staff = (string) Str::uuid();

    expect(fn () => RegisterRewardPayeeAction::run(
        'user',
        $memberId,
        'paypal',
        'member@example.test',
        'Member PayPal',
        'user',
        $memberId,
        'member',
    ))->toThrow(InvalidArgumentException::class, 'Payout method [paypal] is not configured.');

    expect(fn () => VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'paypal',
        'member@example.test',
        'Member PayPal',
        'user',
        $staff,
        'staff',
    ))->toThrow(InvalidArgumentException::class, 'Payout method [paypal] is not configured.');

    expect(RewardPayee::query()->count())->toBe(0);
});

it('refuses recipient-owned payout submission and cancellation', function (): void {
    [$program, $award, $memberId, $staff] = payoutHarness('pay-own-1');

    $request = RequestRewardPayoutAction::run(
        (string) $award->getKey(),
        RewardPayee::query()->where('recipient_id', $memberId)->where('status', 'verified')->firstOrFail(),
        'manual',
        'program',
        'user',
        $staff,
        'staff',
    );

    expect(fn () => SubmitRewardPayoutAction::run($request, 'user', $memberId, 'staff'))
        ->toThrow(InvalidArgumentException::class, 'Recipient-owned payout submission is prohibited.');

    expect(fn () => CancelRewardPayoutAction::run($request, 'user', $memberId, 'staff', 'changed-mind'))
        ->toThrow(InvalidArgumentException::class, 'Recipient-owned payout cancellation is prohibited.');

    expect($request->refresh()->status)->toBe(RewardPayoutRequestStatus::Requested);
});

it('refuses stale or mismatched payee registrations without replacing the current destination', function (): void {
    $memberId = (string) Str::uuid();
    $staff = RewardLedgerFixtures::staffId();

    $verified = VerifyRewardPayeeAction::run('user', $memberId, 'manual', 'acct-live', 'Live destination', 'user', $staff, 'staff');
    $old = RegisterRewardPayeeAction::run('user', $memberId, 'manual', 'acct-old', 'Old registration', 'user', $memberId, 'member');
    $current = RegisterRewardPayeeAction::run('user', $memberId, 'manual', 'acct-new', 'Current registration', 'user', $memberId, 'member');

    expect(fn () => VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'acct-old',
        'Old registration',
        'user',
        $staff,
        'staff',
        ['verified_from_registration_id' => (string) $old->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'matching current pending registration');

    expect(fn () => VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'acct-old',
        'Old registration',
        'user',
        $staff,
        'staff',
        ['verified_from_registration_id' => (string) $current->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'matching current pending registration');

    expect($verified->refresh()->status)->toBe(RewardPayeeStatus::Verified)
        ->and($old->refresh()->status)->toBe(RewardPayeeStatus::Superseded)
        ->and($current->refresh()->status)->toBe(RewardPayeeStatus::Pending)
        ->and(RewardPayee::query()->where('recipient_id', $memberId)->count())->toBe(3);

    $replacement = VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'acct-new',
        'Current registration',
        'user',
        $staff,
        'staff',
        ['verified_from_registration_id' => (string) $current->getKey()],
    );

    expect($replacement->status)->toBe(RewardPayeeStatus::Verified)
        ->and($verified->refresh()->status)->toBe(RewardPayeeStatus::Superseded)
        ->and($current->refresh()->status)->toBe(RewardPayeeStatus::Superseded);

    expect(fn () => VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'acct-new',
        'Current registration',
        'user',
        $staff,
        'staff',
        ['verified_from_registration_id' => (string) $current->getKey()],
    ))->toThrow(InvalidArgumentException::class, 'matching current pending registration');

    expect($replacement->refresh()->status)->toBe(RewardPayeeStatus::Verified)
        ->and(RewardPayee::query()->where('recipient_id', $memberId)->count())->toBe(4);
});

it('selects only verified payees belonging to the scoped award recipient', function (): void {
    [, $award, $memberId, $staff] = payoutHarness('pay-select');
    $awardId = (string) $award->getKey();
    $verified = RewardPayee::query()->where('recipient_id', $memberId)->where('status', RewardPayeeStatus::Verified)->firstOrFail();
    $pending = RewardPayee::factory()->create(['recipient_id' => $memberId]);
    $foreign = RewardPayee::factory()->create([
        'recipient_id' => (string) Str::uuid(),
        'status' => RewardPayeeStatus::Verified,
        'verified_at' => now(),
        'verified_by' => $staff,
    ]);

    $selection = app(RewardPayeeSelectionApi::class);

    expect($selection->optionsForAward($awardId))->toBe([(string) $verified->getKey() => 'manual — Manual handoff'])
        ->and($selection->methodForAward($awardId, (string) $verified->getKey()))->toBe('manual')
        ->and($selection->methodForAward($awardId, (string) $pending->getKey()))->toBeNull()
        ->and($selection->methodForAward($awardId, (string) $foreign->getKey()))->toBeNull()
        ->and($selection->optionsForAward((string) Str::uuid()))->toBe([]);
});
