<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class ConversionReportRow
{
    public function __construct(
        public ?string $currency,
        public int $conversions,
        public int $revenueMinor,
        public int $commissionMinor,
        public ?string $affiliateId = null,
        public ?string $affiliateCode = null,
        public ?string $date = null,
        public ?string $subjectType = null,
        public ?string $subjectKey = null,
        public ?string $subjectTitleSnapshot = null,
    ) {}
}
