<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Contracts;

use AIArmada\Ticketing\Enums\PricingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface TicketableInterface
{
    public function ticketTypes(): MorphMany;

    public function passes(): MorphMany;

    public function effectivePricingMode(): PricingMode;

    public function transferWindowEndsAt(): ?CarbonImmutable;
}
