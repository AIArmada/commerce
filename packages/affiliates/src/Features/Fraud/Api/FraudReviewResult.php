<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

final readonly class FraudReviewResult
{
    public function __construct(
        public string $signalId,
        public ?string $linkedRejectionError,
    ) {}
}
