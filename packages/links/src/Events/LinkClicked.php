<?php

declare(strict_types=1);

namespace AIArmada\Links\Events;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Models\LinkClick;

final class LinkClicked
{
    public function __construct(
        public readonly Link $link,
        public readonly LinkClick $click,
    ) {}
}
