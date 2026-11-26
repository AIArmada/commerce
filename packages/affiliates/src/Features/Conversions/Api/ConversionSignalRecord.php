<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use AIArmada\Affiliates\Data\AffiliateEventReference;

final readonly class ConversionSignalRecord
{
    public function __construct(
        public AffiliateEventReference $reference,
        public ?string $attributionId,
        public ?string $subjectKey,
        public ?string $subjectInstance,
        public ?string $voucherCode,
        public ?string $externalReference,
        public ?string $conversionType,
        public int $subtotalMinor,
        public int $valueMinor,
        public int $commissionMinor,
        public string $commissionCurrency,
        public string $status,
        public ?string $channel,
        public ?string $occurredAt,
    ) {}
}
