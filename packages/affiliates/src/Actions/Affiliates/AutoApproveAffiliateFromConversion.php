<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;

final readonly class AutoApproveAffiliateFromConversion
{
    public function __construct(private QualifiesForOpenApproval $policy, private ApproveAffiliate $approve) {}

    public function execute(string $conversionId): void
    {
        if (! $this->policy->qualifies($conversionId)) {
            return;
        }

        (new AffiliateConversion)->getConnection()->transaction(function () use ($conversionId): void {
            $conversion = AffiliateConversion::query()->whereKey($conversionId)->lockForUpdate()->first();

            if (! $conversion instanceof AffiliateConversion) {
                return;
            }

            $affiliate = Affiliate::query()->whereKey($conversion->affiliate_id)->lockForUpdate()->first();

            if (! $affiliate instanceof Affiliate || ! $this->policy->qualifies($conversionId)) {
                return;
            }

            $affiliate->metadata = array_merge($affiliate->metadata ?? [], ['open_approved_by_conversion' => $conversionId]);
            $this->approve->handle($affiliate);
        }, attempts: 3);
    }
}
