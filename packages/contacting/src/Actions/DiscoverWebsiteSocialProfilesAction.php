<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\Contacting\Support\SocialProfileConfig;
use DOMDocument;
use DOMElement;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class DiscoverWebsiteSocialProfilesAction
{
    use AsAction;

    private const int MAX_HTML_BYTES = 524288;

    public function __construct(
        private readonly SocialProfileConfig $socialProfileConfig,
        private readonly ?PublicHttpUrlGuard $guard = null,
        private readonly ?PinnedHttpClient $http = null,
    ) {}

    /**
     * @return list<array{platform: string, handle: string, url: string, is_public: bool, is_primary: bool}>
     */
    public function handle(?string $websiteUrl): array
    {
        if (! is_string($websiteUrl)) {
            return [];
        }

        $directProfiles = $this->extractSocialProfilesFromUrls([$websiteUrl]);

        if ($directProfiles !== []) {
            return $directProfiles;
        }

        try {
            $html = $this->fetchPublicHtml($websiteUrl);
        } catch (Throwable) {
            return [];
        }

        return $html === null
            ? []
            : array_merge($directProfiles, $this->extractSocialProfiles($html));
    }

    /**
     * @param  list<string>  $urls
     * @return list<array{platform: string, handle: string, url: string, is_public: bool, is_primary: bool}>
     */
    public function extractSocialProfilesFromUrls(array $urls): array
    {
        $profiles = [];

        foreach ($urls as $url) {
            if (! $this->isAbsoluteHttpUrl($url)) {
                continue;
            }

            foreach (SocialPlatform::cases() as $platform) {
                if (in_array($platform, [SocialPlatform::Website, SocialPlatform::Other], true)) {
                    continue;
                }

                $handle = $this->socialProfileConfig->extractHandle($platform->value, $url);

                if (! is_string($handle) || $handle === '') {
                    continue;
                }

                $profiles[] = [
                    'platform' => $platform->value,
                    'handle' => $handle,
                    'url' => $url,
                    'is_public' => true,
                    'is_primary' => false,
                ];

                break;
            }
        }

        return $this->uniqueProfiles($profiles);
    }

    /**
     * @return list<array{platform: string, handle: string, url: string, is_public: bool, is_primary: bool}>
     */
    public function extractSocialProfiles(string $html): array
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $profiles = [];
        $seen = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $url = mb_trim($anchor->getAttribute('href'));

            if (! $this->isAbsoluteHttpUrl($url)) {
                continue;
            }

            foreach (SocialPlatform::cases() as $platform) {
                if (in_array($platform, [SocialPlatform::Website, SocialPlatform::Other], true)) {
                    continue;
                }

                $handle = $this->socialProfileConfig->extractHandle($platform->value, $url);

                if (! is_string($handle) || $handle === '' || isset($seen[$platform->value . '|' . $handle])) {
                    continue;
                }

                $seen[$platform->value . '|' . $handle] = true;
                $profiles[] = [
                    'platform' => $platform->value,
                    'handle' => $handle,
                    'url' => $url,
                    'is_public' => true,
                    'is_primary' => false,
                ];

                break;
            }

            if (count($profiles) >= 30) {
                break;
            }
        }

        return $this->uniqueProfiles($profiles);
    }

    private function fetchPublicHtml(string $url): ?string
    {
        $guard = $this->guard ?? app(PublicHttpUrlGuard::class);
        $http = $this->http ?? app(PinnedHttpClient::class);

        $response = $http->downloadToLimit(
            guard: $guard,
            url: $url,
            maxBytes: self::MAX_HTML_BYTES,
            headers: ['Accept' => 'text/html'],
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

        return $http->readBoundedBody($response, self::MAX_HTML_BYTES);
    }

    /**
     * @param  list<array{platform: string, handle: string, url: string, is_public: bool, is_primary: bool}>  $profiles
     * @return list<array{platform: string, handle: string, url: string, is_public: bool, is_primary: bool}>
     */
    private function uniqueProfiles(array $profiles): array
    {
        return collect($profiles)
            ->unique(static fn (array $profile): string => $profile['platform'] . '|' . $profile['handle'])
            ->values()
            ->all();
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
