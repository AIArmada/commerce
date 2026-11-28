<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class CreatedTrackingTokenData
{
    public function __construct(
        public string $trackingTokenId,
        public string $plaintextToken,
    ) {}
}
