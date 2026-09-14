<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

final class BackToUrlSanitizer
{
    /**
     * Sanitize a back-to URL to prevent open redirects.
     *
     * Accepts relative paths (e.g. /admin) and absolute same-host URLs.
     * Rejects cross-host URLs, backslashes (`/\evil.com` parses as
     * `//evil.com` under WHATWG URL rules), and control characters.
     */
    public static function sanitize(?string $url): string
    {
        if (! is_string($url) || $url === '') {
            return '/';
        }

        if (str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return '/';
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'])) {
            return '/';
        }

        $requestHost = request()->getHost();

        if (mb_strtolower((string) $parsed['host']) !== mb_strtolower($requestHost)) {
            return '/';
        }

        return $url;
    }
}
