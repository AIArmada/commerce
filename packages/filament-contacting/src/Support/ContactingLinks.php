<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Support;

final class ContactingLinks
{
    public static function safeHttpUrl(?string $url): ?string
    {
        if ($url === null || mb_trim($url) === '') {
            return null;
        }

        $trimmed = mb_trim($url);
        $scheme = mb_strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $trimmed;
    }
}
