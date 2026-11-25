<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventRefundStateData
{
    public function __construct(
        public string $id,
        public string $status,
        public bool $providerSubmissionStarted,
        public int $amount,
        public ?string $reason = null,
    ) {}
}
