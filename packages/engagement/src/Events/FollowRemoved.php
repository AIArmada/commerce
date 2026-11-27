<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Features\Interactions\Models\Follow;

final class FollowRemoved
{
    public function __construct(public Follow $follow) {}
}
