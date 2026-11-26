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
        $target = $this->urlGuard->validate($pageUrl);
        $response = $this->httpClient->send(
            method: 'GET',
            target: $target,
            options: defined('CURLOPT_MAXFILESIZE') ? ['curl' => [CURLOPT_MAXFILESIZE => self::MAX_HTML_BYTES]] : [],
            headers: ['Accept' => 'text/html'],
            connectTimeout: 3,
            timeout: 15,
        );

        if (! $response->successful()) {
            return null;
        }

        $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        $body = $response->body();

        return strlen($body) <= self::MAX_HTML_BYTES ? $body : null;
    }
}
