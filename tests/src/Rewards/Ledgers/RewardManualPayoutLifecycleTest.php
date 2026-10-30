<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Rewards\Actions\ApproveRewardPayoutAction;
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
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Actions\VerifyRewardPayeeAction;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardBudgetMovementKind;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardAwardEntry;
use AIArmada\Rewards\Models\RewardBudgetMovement;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Support\FundedRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\FundedRewardReadinessChecker;
use AIArmada\Rewards\Support\ManualRewardPayoutExecutor;
use AIArmada\Rewards\Support\ManualRewardPayoutMethodCatalog;
use AIArmada\Rewards\Support\RewardBudgetLedger;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Builds a funded cash program and runs the shipped manual lifecycle up to
 * either Submitted or Confirmed, returning everything the assertions need.
 *
 * @return array{0: RewardProgram, 1: RewardAward, 2: RewardPayoutRequest, 3: string}
 */
function nativeManualPayout(string $prefix, bool $confirm = true): array
{
    $reader = new FakeRewardSourceFactReader;
    app()->instance(RewardSourceFactReader::class, $reader);

    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram($prefix, RewardProgramMode::Cash);

    $memberId = (string) User::factory()->create()->getKey();
    $staff = (string) Str::uuid();

    RecordRewardFundingSourceAction::run($prefix . '-source', 'MYR', 10000, 'user', $staff, 'staff');
    RecordRewardFundingAction::run($program, 'MYR', 10000, $prefix . '-source', 'user', $staff, 'staff');

    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    EnrollRewardMemberAction::run(
        $program,
        'user',
        $memberId,
        $version,
        'member-portal',
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
    );

    $reader->seed(RewardLedgerFixtures::fact($prefix . '-attendance', $memberId));

    $result = EvaluateRewardEntitlementAction::run(
        $program,
        RewardComponent::Cash,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        $prefix . '-attendance',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $award = $result->award->refresh();

    RegisterRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        $prefix . '-account',
        'Manual handoff',
        'user',
        $memberId,
        'member',
    );

    $payee = VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        $prefix . '-account',
        'Manual handoff',
        'user',
        $staff,
        'staff',
    );

    $award = TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Approved,
        'user',
        $staff,
        'staff',
        'source-and-funding-verified',
        null,
        $prefix . '-approve',
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    $request = RequestRewardPayoutAction::run(
        $award,
        $payee,
        'manual',
        'program',
        'user',
        $staff,
        'staff',
    );

    $request = ApproveRewardPayoutAction::run(
        $request,
        'user',
        (string) Str::uuid(),
        'staff',
        'independent-finance-approval',
    );

    $request = SubmitRewardPayoutAction::run($request, 'user', $staff, 'staff');

    if ($confirm) {
        $request = ConfirmRewardPayoutAction::run(
            $request,
            [
                'bank_reference' => $prefix . '-bank-1',
                'transferred_at' => '2026-06-17T00:00:00Z',
            ],
            'user',
            (string) Str::uuid(),
            'staff',
        );
    }

    return [$program->refresh(), $award->refresh(), $request->refresh(), $staff];
}

it('fulfills a funded cash award through the shipped manual payout lifecycle', function (): void {
    Http::preventStrayRequests();

    $affiliateWrites = [];

    DB::listen(function (QueryExecuted $query) use (&$affiliateWrites): void {
        if (preg_match('/^\s*(insert|update|delete|replace|merge)\b/i', $query->sql) === 1
            && str_contains(mb_strtolower($query->sql), 'affiliate')) {
            $affiliateWrites[] = $query->sql;
        }
    });

    expect(app(RewardPayoutExecutor::class))
        ->toBeInstanceOf(ManualRewardPayoutExecutor::class);

    expect(app(RewardPayoutMethodCatalog::class))
        ->toBeInstanceOf(ManualRewardPayoutMethodCatalog::class);

    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    $reader = new FakeRewardSourceFactReader;
    app()->instance(RewardSourceFactReader::class, $reader);

    $this->travelTo(CarbonImmutable::parse('2026-06-05T00:00:00Z'));

    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram(
        'native-manual',
        RewardProgramMode::Cash,
    );

    $memberId = (string) User::factory()->create()->getKey();
    $staffId = (string) Str::uuid();
    $approverId = (string) Str::uuid();
    $confirmerId = (string) Str::uuid();

    RecordRewardFundingSourceAction::run(
        'native-manual-source',
        'MYR',
        10000,
        'user',
        $staffId,
        'staff',
    );

    RecordRewardFundingAction::run(
        $program,
        'MYR',
        10000,
        'native-manual-source',
        'user',
        $staffId,
        'staff',
    );

    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    $this->travelTo(CarbonImmutable::parse('2026-06-10T00:00:00Z'));

    EnrollRewardMemberAction::run(
        $program,
        'user',
        $memberId,
        $version,
        'member-portal',
    );

    $reader->seed(RewardLedgerFixtures::fact('native-manual-attendance', $memberId));

    $this->travelTo(CarbonImmutable::parse('2026-06-15T00:00:00Z'));

    $result = EvaluateRewardEntitlementAction::run(
        $program,
        RewardComponent::Cash,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'native-manual-attendance',
        CarbonImmutable::now('UTC'),
    );

    $award = $result->award->refresh();

    expect($award->status)->toBe(RewardAwardStatus::Held);

    $this->travelTo(CarbonImmutable::parse('2026-06-16T00:00:00Z'));

    RegisterRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'native-manual-account',
        'Manual handoff',
        'user',
        $memberId,
        'member',
    );

    $payee = VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'native-manual-account',
        'Manual handoff',
        'user',
        $staffId,
        'staff',
    );

    $award = TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Approved,
        'user',
        $staffId,
        'staff',
        'source-and-funding-verified',
    );

    $request = RequestRewardPayoutAction::run(
        $award,
        $payee,
        'manual',
        'program',
        'user',
        $staffId,
        'staff',
    );

    $request = ApproveRewardPayoutAction::run(
        $request,
        'user',
        $approverId,
        'staff',
        'independent-finance-approval',
    );

    $request = SubmitRewardPayoutAction::run(
        $request,
        'user',
        $staffId,
        'staff',
    );

    expect($request->status)->toBe(RewardPayoutRequestStatus::Submitted);
    expect($request->external_reference_id)->toBeNull();
    expect($request->external_payout_id)
        ->toBe('manual:reward-payout:' . $award->getKey());

    expect(fn () => TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Fulfilled,
        'user',
        $staffId,
        'staff',
        'too-early',
    ))->toThrow(InvalidArgumentException::class, 'payout-not-confirmed:submitted');

    $request = ReconcileRewardPayoutAction::run(
        $request,
        'user',
        $staffId,
        'staff',
    );

    expect($request->status)->toBe(RewardPayoutRequestStatus::Submitted);
    expect($request->confirmed_at)->toBeNull();

    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR'))->toBe([
        'funded' => 10000,
        'reserved' => 100,
        'net_spent' => 0,
        'available' => 9900,
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-06-17T00:00:00Z'));

    $request = ConfirmRewardPayoutAction::run(
        $request,
        [
            'bank_reference' => 'native-bank-1',
            'transferred_at' => '2026-06-17T00:00:00Z',
        ],
        'user',
        $confirmerId,
        'staff',
    );

    expect($request->status)->toBe(RewardPayoutRequestStatus::Confirmed);
    expect($request->confirmed_at?->toIso8601String())
        ->toBe('2026-06-17T00:00:00+00:00');
    expect($request->provider_reference)->toBe('native-bank-1');
    expect($request->external_reference_id)->toBeNull();

    $award = TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Fulfilled,
        'user',
        $staffId,
        'staff',
        'manual-transfer-confirmed',
        null,
        'native-manual-fulfill',
    );

    $fulfilledAt = $award->fulfilled_at?->toIso8601String();

    $this->travelTo(CarbonImmutable::parse('2026-06-18T00:00:00Z'));

    $award = TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Fulfilled,
        'user',
        $staffId,
        'staff',
        'manual-transfer-confirmed',
        null,
        'native-manual-fulfill',
    );

    expect($award->status)->toBe(RewardAwardStatus::Fulfilled);
    expect($award->fulfilled_at?->toIso8601String())->toBe($fulfilledAt);

    expect(RewardAwardEntry::query()
        ->where('award_id', $award->getKey())
        ->where('to_status', RewardAwardStatus::Fulfilled->value)
        ->count())->toBe(1);

    expect(RewardBudgetMovement::query()
        ->where('award_id', $award->getKey())
        ->where('kind', RewardBudgetMovementKind::Spend)
        ->count())->toBe(1);

    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR'))->toBe([
        'funded' => 10000,
        'reserved' => 0,
        'net_spent' => 100,
        'available' => 9900,
    ]);

    expect($affiliateWrites)->toBe([]);
    Http::assertNothingSent();
});

it('refuses a confirmed payout without its operation identity', function (): void {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    [$program, $award, $request, $staff] = nativeManualPayout('native-no-operation');

    RewardPayoutRequest::query()->whereKey($request->getKey())->update([
        'external_payout_id' => null,
    ]);

    $request->refresh();

    expect(fn () => TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Fulfilled,
        'user',
        $staff,
        'staff',
        'missing-operation',
    ))->toThrow(
        InvalidArgumentException::class,
        'cash fulfillment requires a confirmed payout timestamp and a payout operation',
    );

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Approved);
    expect(RewardAwardEntry::query()
        ->where('award_id', $award->getKey())
        ->where('to_status', RewardAwardStatus::Fulfilled->value)
        ->exists())->toBeFalse();
    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe(0);
    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['net_spent'])->toBe(100);
});

it('refuses a confirmed payout without its confirmation timestamp', function (): void {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    [$program, $award, $request, $staff] = nativeManualPayout('native-no-timestamp');

    RewardPayoutRequest::query()->whereKey($request->getKey())->update([
        'confirmed_at' => null,
    ]);

    $request->refresh();

    expect(fn () => TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Fulfilled,
        'user',
        $staff,
        'staff',
        'missing-timestamp',
    ))->toThrow(
        InvalidArgumentException::class,
        'cash fulfillment requires a confirmed payout timestamp and a payout operation',
    );

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Approved);
    expect(RewardAwardEntry::query()
        ->where('award_id', $award->getKey())
        ->where('to_status', RewardAwardStatus::Fulfilled->value)
        ->exists())->toBeFalse();
    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['reserved'])->toBe(0);
    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['net_spent'])->toBe(100);
});

it('does not fulfill against incoherent frozen payout terms', function (): void {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    [$program, $award, $request, $staff] = nativeManualPayout('native-incoherent');

    $snapshot = $request->payee_snapshot;
    $snapshot['payee_id'] = (string) Str::uuid();

    RewardPayoutRequest::query()->whereKey($request->getKey())->update([
        'payee_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
    ]);

    $request->refresh();

    expect(fn () => TransitionRewardAwardAction::run(
        $award,
        RewardAwardStatus::Fulfilled,
        'user',
        $staff,
        'staff',
        'incoherent-terms',
    ))->toThrow(InvalidArgumentException::class, 'payout-terms-incoherent');

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Approved);
    expect(RewardAwardEntry::query()
        ->where('award_id', $award->getKey())
        ->where('to_status', RewardAwardStatus::Fulfilled->value)
        ->exists())->toBeFalse();
    expect(RewardBudgetLedger::balances((string) $program->getKey(), 'MYR')['net_spent'])->toBe(100);
});
