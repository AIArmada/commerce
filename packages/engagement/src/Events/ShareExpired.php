<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Features\Interactions\Models\Share;

final class ShareExpired
{
    public function __construct(public Share $share) {}
}
