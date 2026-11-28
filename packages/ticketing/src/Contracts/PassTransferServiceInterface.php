<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;

interface PassTransferServiceInterface
{
    public function transfer(Pass $pass, PassHolder $newHolder, ?string $reason = null): PassHolder;

    public function canTransfer(Pass $pass): bool;
}
