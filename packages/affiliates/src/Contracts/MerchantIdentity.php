<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\MerchantAffiliate;

/**
 * Merchant identity seam.
 *
 * Recognizes merchant affiliates for reporting and linkage. Recognition
 * never gates enrollment: unknown ids simply resolve to null.
 */
interface MerchantIdentity
{
    public function find(string $id): ?MerchantAffiliate;

    /**
     * Resolve an Active affiliate id by verified contact email.
     */
    public function findIdForEmail(string $email): ?string;
}
