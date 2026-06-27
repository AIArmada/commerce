<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum BookmarkStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
    case Archived = 'archived';
}
