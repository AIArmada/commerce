<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use Carbon\CarbonImmutable;

final readonly class AttributionTouch
{
    public function __construct(
        public string $affiliateId,
        public ?CarbonImmutable $touchedAt,
    ) {}
}
