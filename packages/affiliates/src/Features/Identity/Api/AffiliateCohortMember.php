<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;

final readonly class AffiliateCohortMember
{
    public function __construct(
        public string $id,
        public bool $active,
        public ?CarbonImmutable $deactivatedAt,
    ) {}
}
