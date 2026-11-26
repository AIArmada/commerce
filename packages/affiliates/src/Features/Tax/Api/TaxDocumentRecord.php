<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Api;

final readonly class TaxDocumentRecord
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $documentType,
        public int $year,
        public TaxDocumentStatus $status,
        public int $amountMinor,
        public string $currency,
        public ?string $notes,
        public ?string $generatedAt,
        public ?string $sentAt,
    ) {}
}
