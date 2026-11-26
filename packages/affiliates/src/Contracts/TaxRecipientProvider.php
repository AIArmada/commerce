<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\TaxRecipientDetails;

interface TaxRecipientProvider
{
    public function find(string $affiliateId): ?TaxRecipientDetails;
}
