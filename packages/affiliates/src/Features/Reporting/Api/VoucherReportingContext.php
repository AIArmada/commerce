<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

final readonly class VoucherReportingContext
{
    public function __construct(
        public ?string $affiliateCode,
        public ?string $affiliateName,
        public ?string $source,
        public ?string $medium,
        public ?string $campaign,
    ) {}

    /** @return array{affiliate_code: string|null, affiliate_name: string|null, source: string|null, medium: string|null, campaign: string|null} */
    public function toArray(): array
    {
        return [
            'affiliate_code' => $this->affiliateCode,
            'affiliate_name' => $this->affiliateName,
            'source' => $this->source,
            'medium' => $this->medium,
            'campaign' => $this->campaign,
        ];
    }
}
