<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Drivers;

use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\Contacting\Contracts\PublicHtmlFetcher;

final readonly class PinnedPublicHtmlFetcher implements PublicHtmlFetcher
{
    private const int MAX_HTML_BYTES = 524288;

    public function __construct(
        private PublicHttpUrlGuard $guard,
        private PinnedHttpClient $http,
    ) {}

    public function fetch(string $url): ?string
    {
        $response = $this->http->downloadToLimit(
            guard: $this->guard,
            url: $url,
            maxBytes: self::MAX_HTML_BYTES,
            maxRedirects: 3,
            connectTimeout: 2,
            timeout: 5,
        );

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $contentType = mb_strtolower((string) $response->header('Content-Type'));

        if (! str_contains($contentType, 'text/html')) {
            return null;
        }

        return $this->http->readBoundedBody($response, self::MAX_HTML_BYTES);
    }
}
