<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class PlannedDeliveryData
{
    public function __construct(
        public string $recipientId,
        public string $channel,
        public string $destinationHash,
        public string $destinationHint,
        public string $destinationCiphertext,
        public string | null $contentId = null,
        public int | null $maxAttempts = null,
        public string | null $scheduledAt = null,
    ) {}
}
