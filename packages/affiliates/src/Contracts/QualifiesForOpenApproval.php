<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Models\AffiliateConversion;

interface QualifiesForOpenApproval
{
    public function qualifies(AffiliateConversion $conversion): bool;
}
