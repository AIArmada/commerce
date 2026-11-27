<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class SuppressionDecisionData
{
    public function __construct(
        public bool $suppressed,
        public string | null $reason = null,
        public string | null $suppressionId = null,
        public array $metadata = [],
    ) {}
}
