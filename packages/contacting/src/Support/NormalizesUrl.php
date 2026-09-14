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

        if ($url === '') {
            return null;
        }

        // Reject non-http/https URLs explicitly
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:\/\//', $url)) {
            if (preg_match('#^https?://#i', $url) !== 1) {
                return null;
            }

            $normalized = preg_replace_callback(
                '#^(https?)://([^/]*)#i',
                static fn (array $matches): string => mb_strtolower($matches[1]) . '://' . mb_strtolower($matches[2]),
                $url,
            );

            return is_string($normalized) ? $normalized : null;
        }

        // If URL has no scheme and looks like a domain, prepend https://
        if (str_contains($url, '.') || str_contains($url, 'localhost')) {
            return 'https://' . $url;
        }

        return null;
    }
}
