<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Share;

final class ShareCreated
{
    public function __construct(public Share $share) {}
}
