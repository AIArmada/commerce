<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class SuppressionDecisionData extends Data
{
    public function __construct(
        public readonly bool $suppressed,
        public readonly string | null | Optional $reason = null,
        public readonly string | null | Optional $suppressionId = null,
        public readonly array $metadata = [],
    ) {}
}
