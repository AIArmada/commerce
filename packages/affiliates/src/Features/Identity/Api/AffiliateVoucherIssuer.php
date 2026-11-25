<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateVoucherIssuer
{
    public function connectionName(): string;

    /** Returns the newly issued code, or null when an existing voucher already covers this affiliate. */
    public function issue(AffiliateLifecycleRecord $affiliate): ?string;
}
