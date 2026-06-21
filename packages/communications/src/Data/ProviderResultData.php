<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ProviderResultData extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly string | null | Optional $providerMessageId = null,
        public readonly string | null | Optional $status = null,
        public readonly array $response = [],
        public readonly int | null | Optional $durationMs = null,
        public readonly int | null | Optional $costMinor = null,
        public readonly string | null | Optional $costCurrency = null,
        public readonly string | null | Optional $failureCode = null,
        public readonly string | null | Optional $failureMessage = null,
        public readonly bool | null | Optional $retryable = null,
    ) {}
}
