<?php

declare(strict_types=1);

namespace AIArmada\Links\Contracts;

use AIArmada\Links\Models\Link;

interface FallbackRedirectGateInterface
{
    public function fallbackUrl(Link $link, string $reason): ?string;
}
