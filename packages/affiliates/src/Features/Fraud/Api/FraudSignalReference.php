<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Api;

final readonly class FraudSignalReference
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public ?string $conversionId,
        public ?string $touchpointId,
    ) {}
}
