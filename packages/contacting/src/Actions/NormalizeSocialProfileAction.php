<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Support\NormalizesSocialHandle;
use AIArmada\Contacting\Support\NormalizesUrl;

final class NormalizeSocialProfileAction
{
    public function __construct(
        private readonly NormalizesSocialHandle $handleNormalizer,
        private readonly NormalizesUrl $urlNormalizer,
    ) {}

    /**
     * @return array{normalized_url: string|null, handle: string|null}
     */
    public function execute(string $platform, ?string $handle, ?string $url): array
    {
        $normalizedHandle = $this->handleNormalizer->normalize($handle);
        $normalizedUrl = $this->urlNormalizer->normalize($url);

        // If URL exists and handle is missing, attempt simple extraction for known platforms
        if ($normalizedHandle === null && $normalizedUrl !== null) {
            $normalizedHandle = $this->extractHandleFromUrl($platform, $normalizedUrl);
        }

        return [
            'normalized_url' => $normalizedUrl,
            'handle' => $normalizedHandle,
        ];
    }

    private function extractHandleFromUrl(string $platform, string $url): ?string
    {
        $patterns = [
            'facebook' => '/facebook\.com\/([^\/\?#]+)/i',
            'instagram' => '/instagram\.com\/([^\/\?#]+)/i',
            'tiktok' => '/tiktok\.com\/(?:@)?([^\/\?#]+)/i',
            'x' => '/x\.com\/([^\/\?#]+)/i',
            'twitter' => '/twitter\.com\/([^\/\?#]+)/i',
            'linkedin' => '/linkedin\.com\/in\/([^\/\?#]+)/i',
            'youtube' => '/youtube\.com\/(?:c\/|channel\/|user\/|@)?([^\/\?#]+)/i',
            'threads' => '/threads\.net\/(?:@)?([^\/\?#]+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if ($key === $platform && preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
