<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\Services\ChipCollectService;

trait InteractsWithChip
{
    /**
     * Get the CHIP Collect service client.
     */
    public static function chip(): ChipCollectService|FakeChipCollectService
    {
        return Cashier::chip();
    }
}
