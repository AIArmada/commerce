<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

final readonly class PayoutMethodProfile
{
    public function __construct(
        public string $type,
        public string $label,
        public string $accountRef,
    ) {}
}
