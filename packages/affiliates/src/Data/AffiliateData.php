<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

final readonly class AffiliateData
{
    /** @param array<string, mixed>|null $metadata */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $status,
        public string $commissionType,
        public int $commissionRate,
        public string $currency,
        public ?string $defaultVoucherCode = null,
        public ?array $metadata = null,
    ) {}

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPercentageCommission(): bool
    {
        return $this->commissionType === 'percentage';
    }

    public function getFormattedCommissionRate(): string
    {
        return $this->isPercentageCommission()
            ? number_format($this->commissionRate / 100, 2) . '%'
            : MoneyFormatter::formatMinorWithCode($this->commissionRate, $this->currency);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'commission_type' => $this->commissionType,
            'commission_rate' => $this->commissionRate,
            'currency' => $this->currency,
            'default_voucher_code' => $this->defaultVoucherCode,
            'metadata' => $this->metadata,
        ];
    }
}
