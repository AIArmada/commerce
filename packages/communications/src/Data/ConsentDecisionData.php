<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class ConsentDecisionData
{
    public function __construct(
        public bool $consented,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}
}
