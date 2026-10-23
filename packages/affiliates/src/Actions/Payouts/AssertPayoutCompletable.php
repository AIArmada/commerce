<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Disabled;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Eligibility gate for provider submission and both payout completion
 * paths (manual status updates and provider reconciliation).
 *
 * Non-payable affiliates refuse unless the payout carries the
 * creation-time audited override for a disabled affiliate, in which
 * case the completion is stamped onto the override record.
 */
final class AssertPayoutCompletable
{
    use AsAction;

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

        $affiliateId = $payout->conversions()->forOwner($owner)->value('affiliate_id');

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

        $conversions = $payout->conversions()->forOwner($owner)->get();

        if ($conversions->contains(static fn (AffiliateConversion $conversion): bool => ! $conversion->status->equals(ApprovedConversion::class))) {
            throw new PayoutCompletionBlockedException('A linked payout conversion has left Approved; reconcile its status before settlement.');
        }

        $operation = $payout->operation()->forOwner($owner)->first();

        if ($operation?->payout_sequence !== null
            && ($operation->funds_released_at !== null
                || $operation->amount_minor !== (int) $payout->total_minor
                || $operation->amount_minor !== (int) $conversions->sum('commission_minor')
                || $conversions->count() !== (int) $payout->conversion_count
                || mb_strtoupper($operation->currency) !== mb_strtoupper($payout->currency))) {
            throw new PayoutCompletionBlockedException('The payout amount or linked conversions no longer match its balance reservation.');
        }

        $onlyRewardAwards = $conversions->isNotEmpty()
            && $conversions->every(static fn (AffiliateConversion $conversion): bool => $conversion->conversion_type === 'reward_award');

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
