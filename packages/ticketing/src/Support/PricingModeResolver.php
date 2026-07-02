<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;

final class PricingModeResolver
{
    /** @param Collection<int, TicketType> $ticketTypes */
    public static function resolve(Collection $ticketTypes): PricingMode
    {
        $hasPaid = false;
        $hasFree = false;

        foreach ($ticketTypes as $type) {
            if ($type->price !== null && $type->price > 0) {
                $hasPaid = true;
            } else {
                $hasFree = true;
            }
        }

        if ($hasPaid && $hasFree) {
            return PricingMode::Mixed;
        }

        return $hasPaid ? PricingMode::Paid : PricingMode::Free;
    }
}
