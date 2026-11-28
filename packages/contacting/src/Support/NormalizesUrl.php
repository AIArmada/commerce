<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Support;

final class NormalizesUrl
{
    public function normalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = mb_trim($url);

        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $url) === 1
                || (! str_contains($url, '.') && ! str_starts_with($url, 'localhost'))) {
                return null;
            }

            $url = 'https://' . $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return preg_replace_callback(
            '~^(https?)://([^/?#]*)~i',
            static fn (array $matches): string => mb_strtolower($matches[1]) . '://' . mb_strtolower($matches[2]),
            $url,
        );
    }
}
