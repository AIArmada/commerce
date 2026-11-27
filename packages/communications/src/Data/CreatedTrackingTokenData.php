<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use AIArmada\Communications\Features\Tracking\Models\CommunicationTrackingToken;

final readonly class CreatedTrackingTokenData
{
    public function __construct(
        public CommunicationTrackingToken $trackingToken,
        public string $plaintextToken,
    ) {}
}
