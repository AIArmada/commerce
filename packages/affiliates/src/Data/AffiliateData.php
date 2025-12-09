<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Immutable projection of an affiliate partner/program.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class AffiliateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly AffiliateStatus $status,
        public readonly CommissionType $commissionType,
        public readonly int $commissionRate,
        public readonly string $currency,
        public readonly ?string $defaultVoucherCode = null,
        public readonly ?array $metadata = null,
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

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active;
    }

    public function isPercentageCommission(): bool
    {
        return $this->commissionType === CommissionType::Percentage;
    }

    public function getFormattedCommissionRate(): string
    {
        if ($this->isPercentageCommission()) {
            return number_format($this->commissionRate / 100, 2) . '%';
        }

        return number_format($this->commissionRate / 100, 2) . ' ' . $this->currency;
    }
}
