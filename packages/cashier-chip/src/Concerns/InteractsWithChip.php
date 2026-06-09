<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\Services\ChipCollectService;

/**
 * Provides gateway access to the CHIP Collect service client.
 *
 * CANONICAL for getting the CHIP API client. Use this trait to
 * access ChipCollectService or its test double.
 *
 * @see InteractsWithPaymentBehavior for subscription payment
 * behavior workflow settings.
 */
trait InteractsWithChip // @phpstan-ignore trait.unused
{
    public static function chip(): ChipCollectService | FakeChipCollectService
    {
        return Cashier::chip();
    }
}
