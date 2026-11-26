<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use Carbon\CarbonImmutable;

final readonly class ConversionFraudEvidence
{
    public function __construct(
        public int $dailyCount,
        public int $dailyLimit,
        public string $date,
        public bool $selfReferral,
        public ?string $ownerId,
        public ?string $actorId,
        public ?CarbonImmutable $clickedAt,
        public ?CarbonImmutable $occurredAt,
        public int $minimumSeconds,
    ) {}
}
