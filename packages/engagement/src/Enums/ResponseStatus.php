<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum ResponseStatus: string
{
    case Active = 'active';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
