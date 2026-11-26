<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Tax;

use AIArmada\Affiliates\Contracts\TaxRecipientProvider;
use AIArmada\Affiliates\Data\TaxRecipientDetails;

final class UnavailableTaxRecipientProvider implements TaxRecipientProvider
{
    public function find(string $affiliateId): ?TaxRecipientDetails
    {
        return null;
    }
}
