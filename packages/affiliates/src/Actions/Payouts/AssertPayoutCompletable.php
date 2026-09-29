<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Disabled;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Single completion gate for both payout completion paths (manual
 * status updates and provider reconciliation).
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
    public function handle(AffiliatePayout $payout): ?string
    {
        // Eligibility evaluates in the payout's own owner scope, never
        // the ambient one: cross-owner console work must resolve the
        // payout's affiliate instead of failing on a scoped miss.
        $owner = $payout->owner;

        // Manual linking bypasses CreatePayout's single-affiliate guard,
        // so the gate pins the affiliate set instead of reading one
        // arbitrary row.
        $affiliateIds = $payout->conversions()->forOwner($owner)->distinct()->pluck('affiliate_id');

        if ($affiliateIds->count() > 1) {
            throw new PayoutCompletionBlockedException(sprintf(
                'Payout [%s] links conversions across %d affiliates; a payout must belong to one affiliate.',
                (string) $payout->getKey(),
                $affiliateIds->count(),
            ));
        }

        $affiliateId = $affiliateIds->first();

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

        if ($affiliate->canReceivePayout()) {
            return null;
        }

        $override = $payout->metadata['payout_override'] ?? null;
        $reason = is_array($override) ? ($override['reason'] ?? null) : null;

        if (! $affiliate->status instanceof Disabled || ! is_string($reason) || mb_trim($reason) === '') {
            throw new PayoutCompletionBlockedException('This payout cannot be completed because the affiliate can no longer receive payouts. Cancel it and re-create with a payout_override_reason if the balance was legitimately earned.');
        }

        $metadata = $payout->metadata ?? [];
        $metadata['payout_override']['override_completed_at'] = CarbonImmutable::now()->toIso8601String();
        $payout->forceFill(['metadata' => $metadata])->save();

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
