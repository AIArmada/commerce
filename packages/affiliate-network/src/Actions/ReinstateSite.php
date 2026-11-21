<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use RuntimeException;

final class ReinstateSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        $site = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();

        if ($site->status === AffiliateSite::STATUS_VERIFIED) {
            return $site;
        }

        if ($site->status !== AffiliateSite::STATUS_SUSPENDED) {
            throw new RuntimeException('Only suspended sites can be reinstated.');
        }

        $site->update([
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verified_at' => $site->verified_at ?? CarbonImmutable::now(),
        ]);

        return $site->refresh();
    }
}
