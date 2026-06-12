<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ContactSnapshotData extends Data
{
    public function __construct(
        public readonly string $snapshotType = '',
        public readonly string | null | Optional $sourceType = null,
        public readonly string | null | Optional $sourceId = null,
        public readonly string | null | Optional $reason = null,
        public readonly string | null | Optional $label = null,
        public readonly string | null | Optional $channel = null,
        public readonly string | null | Optional $value = null,
        public readonly string | null | Optional $normalizedValue = null,
        public readonly string | null | Optional $url = null,
        public readonly string | null | Optional $displayValue = null,
        public readonly bool $isPublic = true,
        public readonly array $payload = [],
        public readonly array $metadata = [],
    ) {}
}
