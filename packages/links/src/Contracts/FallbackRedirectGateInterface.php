<?php

declare(strict_types=1);

namespace AIArmada\Links\Contracts;

use AIArmada\Links\Data\LinkGateContext;

interface FallbackRedirectGateInterface
{
    public function fallbackUrl(LinkGateContext $link, string $reason): ?string;
}
