<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Support\NormalizesSocialHandle;
use AIArmada\Contacting\Support\NormalizesUrl;
use AIArmada\Contacting\Support\SocialProfileConfig;

final class NormalizeSocialProfileAction
{
    public function __construct(
        private readonly SocialProfileConfig $profileConfig,
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

        if ($normalizedHandle !== null && $normalizedUrl === null) {
            $builtUrl = $this->profileConfig->buildUrl($platform, $normalizedHandle);

            if ($builtUrl !== null) {
                $normalizedUrl = $builtUrl;
            }
        }

        if ($normalizedHandle === null && $normalizedUrl !== null) {
            $extractedHandle = $this->profileConfig->extractHandle($platform, $normalizedUrl);

            if ($extractedHandle !== null) {
                $normalizedHandle = $this->handleNormalizer->normalize($extractedHandle);
            }
        }

        return [
            'normalized_url' => $normalizedUrl,
            'handle' => $normalizedHandle,
        ];
    }
}
