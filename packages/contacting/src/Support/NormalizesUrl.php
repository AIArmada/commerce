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
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                return null;
            }

            return $url;
        }

        // If URL has no scheme and looks like a domain, prepend https://
        if (str_contains($url, '.') || str_contains($url, 'localhost')) {
            return 'https://' . $url;
        }

        return null;
    }
}
