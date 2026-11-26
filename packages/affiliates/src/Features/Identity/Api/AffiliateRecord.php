<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class AffiliateRecord
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public bool $active,
        public bool $canBeAttributed,
        public ?string $defaultVoucherCode,
    ) {}
}
