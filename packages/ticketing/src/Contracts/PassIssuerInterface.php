<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Data\PassIssuanceContext;

interface PassIssuerInterface
{
    /** @return list<string> */
    public function issuePassesFor(PassIssuanceContext $context): array;
}
