<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class SocialProfileData extends Data
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly string $platform = '',
        public readonly string $purpose = 'general',
        public readonly string | null | Optional $label = null,
        public readonly string | null | Optional $handle = null,
        public readonly string | null | Optional $url = null,
        public readonly string | null | Optional $normalizedUrl = null,
        public readonly string | null | Optional $displayName = null,
        public readonly string | null | Optional $externalId = null,
        public readonly bool $isPrimary = false,
        public readonly bool $isPublic = true,
        public readonly bool $isVerified = false,
        public readonly mixed $verifiedAt = null,
        public readonly array $metadata = [],
    ) {}
}
