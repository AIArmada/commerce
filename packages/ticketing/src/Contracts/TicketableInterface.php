<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Enums\PricingMode;
use Carbon\CarbonImmutable;

interface TicketableInterface
{
    public function effectivePricingMode(): PricingMode;

    public function transferWindowEndsAt(): ?CarbonImmutable;
}
