<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class RecipientSnapshotData extends Data
{
    public function __construct(
        public readonly string $identifier,
        public readonly string | null | Optional $displayName = null,
        public readonly string | null | Optional $email = null,
        public readonly string | null | Optional $phone = null,
        public readonly string | null | Optional $locale = null,
        public readonly string | null | Optional $timezone = null,
        public readonly array $extra = [],
    ) {}
}
