<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Contracts;

interface SocialProfileNormalizer
{
    public function normalize(string $platform, ?string $handle, ?string $url): array;
}
