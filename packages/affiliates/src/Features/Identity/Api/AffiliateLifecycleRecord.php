<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;

final readonly class AffiliateLifecycleRecord
{
    public function __construct(
        public AffiliateLifecycleReference $reference,
        public string $code,
        public string $name,
        public bool $active,
        public ?CarbonImmutable $createdAt,
        public ?string $registrationApprovalMode,
    ) {}
}
