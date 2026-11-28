<?php

declare(strict_types=1);

namespace AIArmada\Docs\Support;

use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;

final class RichContentUrlGuard
{
    public static function allows(mixed $url, PublicHttpUrlGuard $guard): bool
    {
        if (! is_string($url) || $url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_contains($url, '..');
        }

        if (! str_starts_with(mb_strtolower($url), 'https://') && ! str_starts_with(mb_strtolower($url), 'http://')) {
            return false;
        }

        return $guard->isAllowed($url);
    }
}
