<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class PayoutStatusResult
{
    public function __construct(
        public string $status,
        public ?string $externalReference = null,
    ) {}
}
