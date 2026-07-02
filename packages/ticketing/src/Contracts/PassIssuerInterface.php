<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;

interface PassIssuerInterface
{
    /** @return Collection<int, Pass> */
    public function issuePassesFor(PassIssuanceContext $context): Collection;
}
