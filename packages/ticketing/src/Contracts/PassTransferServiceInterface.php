<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;

interface PassTransferServiceInterface
{
    public function transfer(Pass $pass, PassHolder $newHolder, ?string $reason = null): PassHolder;

    public function canTransfer(Pass $pass): bool;
}
