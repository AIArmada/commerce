<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * DTO representing a conversion + commission record.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class AffiliateConversionData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $affiliateId,
        public readonly string $affiliateCode,
        public readonly ?string $cartIdentifier = null,
        public readonly ?string $cartInstance = null,
        public readonly ?string $voucherCode = null,
        public readonly ?string $orderReference = null,
        public readonly int $subtotalMinor = 0,
        public readonly int $totalMinor = 0,
        public readonly int $commissionMinor = 0,
        public readonly string $commissionCurrency = 'MYR',
        public readonly ConversionStatus $status = ConversionStatus::Pending,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromModel(AffiliateConversion $conversion): self
    {
        $status = $conversion->status;

        if (! $status instanceof ConversionStatus) {
            $status = ConversionStatus::from((string) $status);
        }

        return new self(
            id: (string) $conversion->getKey(),
            affiliateId: (string) $conversion->affiliate_id,
            affiliateCode: (string) $conversion->affiliate_code,
            cartIdentifier: $conversion->cart_identifier,
            cartInstance: $conversion->cart_instance,
            voucherCode: $conversion->voucher_code,
            orderReference: $conversion->order_reference,
            subtotalMinor: (int) $conversion->subtotal_minor,
            totalMinor: (int) $conversion->total_minor,
            commissionMinor: (int) $conversion->commission_minor,
            commissionCurrency: (string) $conversion->commission_currency,
            status: $status,
            occurredAt: $conversion->occurred_at,
            metadata: $conversion->metadata,
        );
    }

    public function isPending(): bool
    {
        return $this->status === ConversionStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ConversionStatus::Approved;
    }

    public function getFormattedCommission(): string
    {
        return number_format($this->commissionMinor / 100, 2) . ' ' . $this->commissionCurrency;
    }

    public function getFormattedTotal(): string
    {
        return number_format($this->totalMinor / 100, 2) . ' ' . $this->commissionCurrency;
    }
}
