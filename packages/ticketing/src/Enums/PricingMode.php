<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

enum PricingMode: string
{
    case Paid = 'paid';
    case Free = 'free';
    case Mixed = 'mixed';
}
