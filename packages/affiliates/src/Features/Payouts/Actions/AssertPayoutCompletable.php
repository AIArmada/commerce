<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Queries\PayoutOperationQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Disabled;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use LogicException;

/**
 * Eligibility gate for provider submission and both payout completion
 * paths (manual status updates and provider reconciliation).
 *
 * Non-payable affiliates refuse unless the payout carries the
 * creation-time audited override for a disabled affiliate, in which
 * case the completion is stamped onto the override record.
 */
final readonly class AssertPayoutCompletable
{
    public function __construct(private PayoutConversionIntegrity $conversions) {}

    /**
     * @return string|null The override reason when this completion rides a creation-time override.
     */
    public function handle(AffiliatePayout $payout, bool $recordCompletion = true): ?string
    {
        // Eligibility evaluates in the payout's own owner scope, never
        // the ambient one: cross-owner console work must resolve the
        // payout's affiliate instead of failing on a scoped miss.
        $owner = $payout->owner;

        try {
            $payout->assertSingleAffiliateConversions(owner: $owner);
        } catch (InvalidArgumentException $exception) {
            throw new PayoutCompletionBlockedException($exception->getMessage(), previous: $exception);
        }

        $summary = $this->conversions->summary(
            (string) $payout->getKey(),
            $payout->owner_type,
            $payout->owner_id,
            spl_object_id($payout->getConnection()),
        );

        if ($summary->foreignOwnerCount !== 0) {
            throw new PayoutCompletionBlockedException('Payout links conversions outside its owner scope.');
        }

        if (count($summary->affiliateIds) > 1) {
            throw new PayoutCompletionBlockedException('Payout links conversions across multiple affiliates.');
        }

        $affiliateId = $summary->affiliateIds[0] ?? null;

        if ($affiliateId === null) {
            // Manual-record payouts link no conversions: eligibility
            // still resolves through the payee so bare payouts cannot
            // skip the gate.
            $affiliateId = $this->resolvePayeeAffiliateId($payout, $owner);

            if ($affiliateId === null) {
                throw new PayoutCompletionBlockedException(sprintf(
                    'Payout [%s] cannot complete: it names no affiliate to check eligibility against.',
                    (string) $payout->getKey(),
                ));
            }
        }

        $affiliate = Affiliate::query()->forOwner($owner)->whereKey($affiliateId)->lockForUpdate()->first();

        if ($affiliate === null) {
            throw new PayoutCompletionBlockedException(sprintf(
                'Payout [%s] cannot complete: affiliate [%s] is missing or outside the payout owner scope.',
                (string) $payout->getKey(),
                (string) $affiliateId,
            ));
        }

        if ((new AffiliatePayoutHold)->getConnection() !== $payout->getConnection()) {
            throw new LogicException('Payout hold verification requires a shared database connection.');
        }

        // The affiliate has already been resolved and locked in the payout's owner scope.
        if (AffiliatePayoutHold::query()
            ->withoutGlobalScope('affiliate_owner')
            ->where('affiliate_id', $affiliate->getKey())
            ->active()
            ->exists()) {
            throw new PayoutCompletionBlockedException('An active payout hold blocks payout completion.');
        }

        if ($summary->approvedCount !== $summary->count) {
            throw new PayoutCompletionBlockedException('A linked payout conversion has left Approved; reconcile its status before settlement.');
        }

        $operation = PayoutOperationQuery::forPayout($payout, $owner)->first();

        if ($operation?->payout_sequence !== null
            && ($operation->affiliate_payout_id !== (string) $payout->getKey()
                || $operation->funds_released_at !== null
                || $operation->amount_minor !== (int) $payout->total_minor
                || $operation->amount_minor !== $summary->totalMinor
                || $summary->count !== (int) $payout->conversion_count
                || mb_strtoupper($operation->currency) !== mb_strtoupper($payout->currency))) {
            throw new PayoutCompletionBlockedException('The payout amount or linked conversions no longer match its balance reservation.');
        }

        $onlyRewardAwards = $summary->count > 0 && $summary->rewardAwardCount === $summary->count;

        if (! $affiliate->isCommercialPurpose()
            && (! $onlyRewardAwards || ! (bool) config('affiliates.payouts.reward_award_settlement.enabled', true))) {
            throw new PayoutCompletionBlockedException('A non-commercial affiliate payout cannot be completed.');
        }

        if ($affiliate->canReceivePayout()) {
            return null;
        }

        $override = $payout->metadata['payout_override'] ?? null;
        $reason = is_array($override) ? ($override['reason'] ?? null) : null;

        if (! $affiliate->status instanceof Disabled || ! is_string($reason) || mb_trim($reason) === '') {
            throw new PayoutCompletionBlockedException('This payout cannot be completed because the affiliate can no longer receive payouts. Cancel it and re-create with a payout_override_reason if the balance was legitimately earned.');
        }

        if ($recordCompletion) {
            $metadata = $payout->metadata ?? [];
            $metadata['payout_override']['override_completed_at'] = CarbonImmutable::now()->toIso8601String();
            $payout->forceFill(['metadata' => $metadata])->save();
        }

        return mb_trim($reason);
    }

    /**
     * Resolve an affiliate id from the payout payee without trusting the
     * ambient scope: the payee morph type may name any model, so only an
     * affiliate target resolves, read in the payout owner scope.
     */
    private function resolvePayeeAffiliateId(AffiliatePayout $payout, mixed $owner): mixed
    {
        if ($payout->payee_type === null || $payout->payee_id === null) {
            return null;
        }

        $target = Relation::getMorphedModel($payout->payee_type) ?? $payout->payee_type;

        if (! is_a($target, Affiliate::class, true)) {
            return null;
        }

        return Affiliate::query()->forOwner($owner)->whereKey($payout->payee_id)->value('id');
    }
}
