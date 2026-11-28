<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

final class AbsoluteHttpUrl
{
    public static function normalize(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $trimmed = mb_trim($url);

        if ($trimmed === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $trimmed) === 1) {
            return null;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($trimmed);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (mb_trim((string) ($parts['host'] ?? '')) === '') {
            return null;
        }

        if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
            return null;
        }

        return $trimmed;
    }
}
