<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateVoucherProvisioning
{
    public function provision(AffiliateLifecycleReference $reference, AffiliateVoucherIssuer $issuer, bool $requireActive, bool $setDefault): void;
}
