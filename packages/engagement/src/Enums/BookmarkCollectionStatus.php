<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum BookmarkCollectionStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Locked = 'locked';
}
