<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class PlannedDeliveryData extends Data
{
    public function __construct(
        public readonly string $recipientId,
        public readonly string $channel,
        public readonly string $destinationHash,
        public readonly string $destinationHint,
        public readonly string $destinationCiphertext,
        public readonly string | null | Optional $contentId = null,
        public readonly int | null | Optional $maxAttempts = null,
        public readonly string | null | Optional $scheduledAt = null,
    ) {}
}
