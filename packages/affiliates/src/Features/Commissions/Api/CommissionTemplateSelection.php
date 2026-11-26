<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

final readonly class CommissionTemplateSelection
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $commissionType,
        public ?int $rate,
    ) {}
}
