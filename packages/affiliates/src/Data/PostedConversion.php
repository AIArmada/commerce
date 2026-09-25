<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

/**
 * A merchant-book posting of an external conversion.
 */
final readonly class PostedConversion
{
    public function __construct(
        public string $id,
        public string $affiliateCode,
        public int $commissionMinor,
        public string $commissionCurrency,
        public string $status,
    ) {}

    /**
     * @return array{id: string, affiliate_code: string, commission_minor: int, commission_currency: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'affiliate_code' => $this->affiliateCode,
            'commission_minor' => $this->commissionMinor,
            'commission_currency' => $this->commissionCurrency,
            'status' => $this->status,
        ];
    }
}
