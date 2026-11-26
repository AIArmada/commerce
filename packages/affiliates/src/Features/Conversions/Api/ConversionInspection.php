<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use Carbon\CarbonImmutable;

final readonly class ConversionInspection
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public ?string $attributionId,
        public ?string $ownerType,
        public ?string $ownerId,
        public ?string $actorUserId,
        public string $status,
        public int $commissionMinor,
        public ?CarbonImmutable $occurredAt,
        public int $connectionIdentity,
    ) {}
}
