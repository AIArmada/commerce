<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;

/**
 * Immutable projection of an affiliate partner/program.
 */
readonly class AffiliateData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public AffiliateStatus $status,
        public CommissionType $commissionType,
        public int $commissionRate,
        public string $currency,
        public ?string $defaultVoucherCode,
        public ?array $metadata,
    ) {}

    public static function fromModel(Affiliate $affiliate): self
    {
        return new self(
            id: (string) $affiliate->getKey(),
            code: (string) $affiliate->code,
            name: (string) $affiliate->name,
            status: $affiliate->status ?? AffiliateStatus::Draft,
            commissionType: $affiliate->commission_type ?? CommissionType::Percentage,
            commissionRate: (int) $affiliate->commission_rate,
            currency: (string) $affiliate->currency,
            defaultVoucherCode: $affiliate->default_voucher_code,
            metadata: $affiliate->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status->value,
            'commission_type' => $this->commissionType->value,
            'commission_rate' => $this->commissionRate,
            'currency' => $this->currency,
            'default_voucher_code' => $this->defaultVoucherCode,
            'metadata' => $this->metadata,
        ];
    }
}
