<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;

final class DefaultLinkGate implements LinkGateInterface
{
    public function blockedReason(Link $link): ?string
    {
        /** @var LinkGateInterface $gate */
        foreach (app()->tagged(LinkGateInterface::class) as $gate) {
            $reason = $gate->blockedReason($link);
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }
}
