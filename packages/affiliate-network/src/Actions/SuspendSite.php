<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use RuntimeException;

final class SuspendSite
{
    public function execute(AffiliateSite $site): AffiliateSite
    {
        $site = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();

        if ($site->status === AffiliateSite::STATUS_SUSPENDED) {
            return $site;
        }

        if ($site->status !== AffiliateSite::STATUS_VERIFIED) {
            throw new RuntimeException('Only verified sites can be suspended.');
        }

        $site->update(['status' => AffiliateSite::STATUS_SUSPENDED]);

        return $site->refresh();
    }
}
