<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class CapturedNotificationData
{
    public function __construct(
        public string $communicationId,
        public string $deliveryId,
    ) {}
}
