<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Drivers;

use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\FilamentSignals\Contracts\PageMarkupFetcher;

final readonly class PinnedPageMarkupFetcher implements PageMarkupFetcher
{
    public const int MAX_HTML_BYTES = 2_097_152;

    public function __construct(
        private PublicHttpUrlGuard $urlGuard,
        private PinnedHttpClient $httpClient,
    ) {}

    public function fetch(string $pageUrl): ?string
    {
        $response = $this->httpClient->downloadToLimit(
            guard: $this->urlGuard,
            url: $pageUrl,
            maxBytes: self::MAX_HTML_BYTES,
            headers: ['Accept' => 'text/html'],
            maxRedirects: 0,
            connectTimeout: 3,
            timeout: 15,
        );

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        return $this->httpClient->readBoundedBody($response, self::MAX_HTML_BYTES);
    }
}
