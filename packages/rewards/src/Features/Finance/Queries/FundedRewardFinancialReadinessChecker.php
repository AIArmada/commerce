<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Queries;

use AIArmada\Rewards\Contracts\RewardBenefitCatalog;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Data\RewardAwardSnapshot;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Data\RewardFinancialReadinessAssessment;
use AIArmada\Rewards\Enums\RewardPayeeStatus;
use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Features\Finance\Models\RewardBudgetMovement;
use AIArmada\Rewards\Features\Finance\Models\RewardPayee;
use AIArmada\Rewards\Features\Finance\Models\RewardPayoutRequest;
use AIArmada\Rewards\Features\Finance\Persistence\RewardBudgetLedger;
use InvalidArgumentException;

/**
 * Real per-award financial readiness: funded budget, verified payee,
 * working fulfillment adapter — each proven from immutable rows.
 *
 * canApproveFinancial: cash needs available budget covering the award
 * value plus program-paid fees, a verified payee for the recipient, and
 * a supported processor. canFulfillFinancial needs the payout request
 * CONFIRMED (paid) with a coherent frozen snapshot: fulfillment stamps
 * only actual receipt, never a submitted-but-unpaid promise, and it
 * never re-checks current funding (already consumed by the operation's
 * own spend) or the CURRENT payee (a later change must not strand the
 * frozen valid operation). Benefit awards consult the benefit catalog
 * adapter when bound; until then they report an explicit blocker
 * instead of silent approval.
 */
final class FundedRewardFinancialReadinessChecker implements RewardFinancialReadinessChecker
{
    public function __construct(
        private readonly RewardPayoutMethodCatalog $catalog,
    ) {}

    public function canApproveFinancial(RewardAwardSnapshot $award): RewardFinancialReadinessAssessment
    {
        if ($award->component === RewardComponent::Benefit->value) {
            return $this->benefitAssessment($award, false);
        }

        if ($award->component !== RewardComponent::Cash->value) {
            return new RewardFinancialReadinessAssessment(ready: true);
        }

        $blockers = [];
        $notes = [];
        $amount = $award->amountSnapshot;
        $minor = $amount['minor_units'] ?? null;
        $currency = $amount['currency'] ?? null;

        if (! is_int($minor) || $minor <= 0 || ! is_string($currency)) {
            return new RewardFinancialReadinessAssessment(
                ready: false,
                blockers: ['cash-amount-unreadable'],
            );
        }

        try {
            $currency = RewardBudgetLedger::normalizeCurrency($currency);
        } catch (InvalidArgumentException) {
            return new RewardFinancialReadinessAssessment(
                ready: false,
                blockers: ['cash-currency-invalid'],
            );
        }

        $balances = RewardBudgetLedger::balances((string) $award->programId, $currency);

        // Own-obligation aware: the held award already reserves its value
        // inside `reserved`, so the gate credits the live obligation back
        // and prices only the uncovered remainder. A retired obligation
        // (rejection/request conversion) credits nothing.
        $ownReserved = 0;
        $epoch = $award->obligationEpoch;
        $obligated = $award->obligatedMinor;

        if (is_int($epoch) && is_int($obligated) && $obligated > 0) {
            $retired = RewardBudgetMovement::query()
                ->where('movement_key', RewardBudgetLedger::releaseObligateKey((string) $award->id, $epoch))
                ->exists();

            if (! $retired) {
                $ownReserved = $obligated;
            }
        }

        if ($minor > $balances['available'] + $ownReserved) {
            $blockers[] = 'insufficient-confirmed-funding';
        } else {
            $notes[] = 'Confirmed funding covers the award value.';
        }

        $payee = RewardPayee::query()
            ->where('recipient_type', (string) $award->recipientType)
            ->where('recipient_id', (string) $award->recipientId)
            ->where('status', RewardPayeeStatus::Verified->value)
            ->orderByDesc('verified_at')
            ->first();

        if (! $payee instanceof RewardPayee) {
            $blockers[] = 'payee-unverified';
        } elseif (! $this->catalog->isSupported((string) $payee->method)) {
            $blockers[] = 'payee-processor-unsupported';
        } else {
            $notes[] = 'Verified payee with a supported processor.';
        }

        if ($blockers !== []) {
            return new RewardFinancialReadinessAssessment(ready: false, blockers: $blockers, notes: $notes);
        }

        return new RewardFinancialReadinessAssessment(ready: true, notes: $notes);
    }

    public function canFulfillFinancial(RewardAwardSnapshot $award): RewardFinancialReadinessAssessment
    {
        if ($award->component === RewardComponent::Benefit->value) {
            return $this->benefitAssessment($award, true);
        }

        if ($award->component !== RewardComponent::Cash->value) {
            return new RewardFinancialReadinessAssessment(ready: true);
        }

        $request = RewardPayoutRequest::query()
            ->where('award_id', $award->id)
            ->first();

        if (! $request instanceof RewardPayoutRequest) {
            return new RewardFinancialReadinessAssessment(
                ready: false,
                blockers: ['payout-not-requested'],
            );
        }

        if ($request->status !== RewardPayoutRequestStatus::Confirmed) {
            return new RewardFinancialReadinessAssessment(
                ready: false,
                blockers: ['payout-not-confirmed:' . $request->status->value],
                notes: ['Cash fulfillment stamps only a confirmed (paid) payout.'],
            );
        }

        $snapshot = $request->payee_snapshot ?? [];

        if (($snapshot['payee_id'] ?? null) !== (string) $request->payee_id
            || ($snapshot['processor'] ?? null) !== (string) $request->processor
            || ! is_string($snapshot['account_ref'] ?? null)
            || ($snapshot['account_ref'] ?? null) === ''
        ) {
            return new RewardFinancialReadinessAssessment(
                ready: false,
                blockers: ['payout-terms-incoherent'],
            );
        }

        return new RewardFinancialReadinessAssessment(
            ready: true,
            notes: ['Payout confirmed against the frozen payee, provider, and destination.'],
        );
    }

    private function benefitAssessment(RewardAwardSnapshot $award, bool $fulfill): RewardFinancialReadinessAssessment
    {
        if (! interface_exists(RewardBenefitCatalog::class) || ! app()->bound(RewardBenefitCatalog::class)) {
            return new RewardFinancialReadinessAssessment(
                ready: false,
                blockers: [$fulfill ? 'benefit-fulfillment-adapter-missing' : 'benefit-catalog-missing'],
                notes: ['Benefit fulfillment arrives with the benefit scope; the award stays a held candidate.'],
            );
        }

        return app(RewardBenefitCatalog::class)->assess($award, $fulfill);
    }
}
