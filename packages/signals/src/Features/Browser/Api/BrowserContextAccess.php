<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Browser\Api;

use AIArmada\Signals\Data\SignalsBrowserContext;

interface BrowserContextAccess
{
    public function current(): ?SignalsBrowserContext;

    public function ensureCurrent(): ?SignalsBrowserContext;
}
