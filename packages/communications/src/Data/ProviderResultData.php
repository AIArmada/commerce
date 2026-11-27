<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class ProviderResultData
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $status = null,
        public array $response = [],
        public ?int $durationMs = null,
        public ?int $costMinor = null,
        public ?string $costCurrency = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public ?bool $retryable = null,
    ) {}
}
