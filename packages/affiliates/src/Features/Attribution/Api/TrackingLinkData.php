<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

final readonly class TrackingLinkData
{
    public function __construct(
        public string $id,
        public string $trackingUrl,
        public ?string $subjectType,
        public ?string $subjectKey,
    ) {}
}
