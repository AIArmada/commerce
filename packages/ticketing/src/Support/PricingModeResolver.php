<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use AIArmada\Ticketing\Data\PricingMode;

final class PricingModeResolver
{
    /** @param iterable<object> $ticketTypes */
    public static function resolve(iterable $ticketTypes): PricingMode
    {
        $hasPaid = false;
        $hasFree = false;

        foreach ($ticketTypes as $type) {
            $price = data_get($type, 'price');

            if ($price !== null && $price > 0) {
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
