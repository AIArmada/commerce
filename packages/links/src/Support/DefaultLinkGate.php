<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;

final class DefaultLinkGate implements LinkGateInterface
{
    public function blockedReason(Link $link): ?string
    {
        return null;
    }
}
