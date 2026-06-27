<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum FollowStatus: string
{
    case Active = 'active';
    case Muted = 'muted';
    case Unfollowed = 'unfollowed';
    case Blocked = 'blocked';
}
