<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class ProviderResultData
{
    public function __construct(
        public bool $success,
        public string | null $providerMessageId = null,
        public string | null $status = null,
        public array $response = [],
        public int | null $durationMs = null,
        public int | null $costMinor = null,
        public string | null $costCurrency = null,
        public string | null $failureCode = null,
        public string | null $failureMessage = null,
        public bool | null $retryable = null,
    ) {}
}
