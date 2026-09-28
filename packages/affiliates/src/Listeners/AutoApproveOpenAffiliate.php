<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Actions\Affiliates\ApproveAffiliate;
use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Facades\DB;

/**
 * Auto-approve open-mode affiliates on their first qualifying conversion.
 */
final class AutoApproveOpenAffiliate
{
    public function __construct(
        private readonly QualifiesForOpenApproval $policy,
        private readonly ApproveAffiliate $approve,
    ) {}

    public function handle(AffiliateConversionRecorded $event): void
    {
        $conversion = AffiliateConversion::query()->whereKey($event->conversion->id)->first();

        if (! $conversion instanceof AffiliateConversion) {
            return;
        }

        if (! $this->policy->qualifies($conversion)) {
            return;
        }

        // Re-evaluate the full policy after taking both locks, so the
        // fraud read sees everything committed before the locks were
        // acquired. Signals committed while this transaction holds the
        // locks (review actions and host code do not take them) land on
        // an active affiliate and are handled by the normal post-activation
        // fraud process instead of blocking approval.
        DB::transaction(function () use ($conversion): void {
            $locked = AffiliateConversion::query()->whereKey($conversion->getKey())->lockForUpdate()->first();

            if (! $locked instanceof AffiliateConversion) {
                return;
            }

            $affiliate = Affiliate::query()->whereKey($locked->affiliate_id)->lockForUpdate()->first();

            if (! $affiliate instanceof Affiliate) {
                return;
            }

            if (! $this->policy->qualifies($locked)) {
                return;
            }

            $affiliate->metadata = array_merge($affiliate->metadata ?? [], [
                'open_approved_by_conversion' => (string) $locked->getKey(),
            ]);

            $this->approve->handle($affiliate);
        }, attempts: 3);
    }
}
