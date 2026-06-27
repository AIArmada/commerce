<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum ReactionStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
