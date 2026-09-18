<?php

declare(strict_types=1);

namespace AIArmada\Links\Events;

use AIArmada\Links\Models\Link;

final class LinkCreated
{
    public function __construct(public readonly Link $link) {}
}
