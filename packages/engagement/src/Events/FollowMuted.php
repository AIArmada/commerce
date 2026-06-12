<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Follow;

final class FollowMuted
{
    public function __construct(public Follow $follow) {}
}
