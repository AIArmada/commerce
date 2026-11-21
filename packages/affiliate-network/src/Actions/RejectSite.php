<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use RuntimeException;

final class RejectSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        $site = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();

        if ($site->status === AffiliateSite::STATUS_REJECTED) {
            return $site;
        }

        if ($site->status !== AffiliateSite::STATUS_PENDING) {
            throw new RuntimeException('Only pending sites can be rejected.');
        }

        $site->update(['status' => AffiliateSite::STATUS_REJECTED]);

        return $site->refresh();
    }
}
