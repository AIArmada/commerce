<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Contracts\FallbackRedirectGateInterface;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;

final class DefaultLinkGate implements FallbackRedirectGateInterface, LinkGateInterface
{
    public function blockedReason(Link $link): ?string
    {
        foreach (app()->tagged(LinkGateInterface::class) as $gate) {
            if (! $gate instanceof LinkGateInterface) {
                continue;
            }

            $reason = $gate->blockedReason($link);

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    public function fallbackUrl(Link $link, string $reason): ?string
    {
        foreach (app()->tagged(LinkGateInterface::class) as $gate) {
            if (! $gate instanceof FallbackRedirectGateInterface) {
                continue;
            }

            $url = $gate->fallbackUrl($link, $reason);

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }
}
