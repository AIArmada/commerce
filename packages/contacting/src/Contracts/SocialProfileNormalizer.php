<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Contracts;

interface SocialProfileNormalizer
{
    /** @return array{normalized_url: string|null, handle: string|null} */
    public function normalize(string $platform, ?string $handle, ?string $url): array;
}
