<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\AffiliateConversion;
use DateTimeInterface;

/**
 * DTO representing a conversion + commission record.
 */
readonly class AffiliateConversionData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $affiliateCode,
        public ?string $cartIdentifier,
        public ?string $cartInstance,
        public ?string $voucherCode,
        public ?string $orderReference,
        public int $subtotalMinor,
        public int $totalMinor,
        public int $commissionMinor,
        public string $commissionCurrency,
        public ConversionStatus $status,
        public ?DateTimeInterface $occurredAt,
        public ?array $metadata,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'affiliate_id' => $this->affiliateId,
            'affiliate_code' => $this->affiliateCode,
            'cart_identifier' => $this->cartIdentifier,
            'cart_instance' => $this->cartInstance,
            'voucher_code' => $this->voucherCode,
            'order_reference' => $this->orderReference,
            'subtotal_minor' => $this->subtotalMinor,
            'total_minor' => $this->totalMinor,
            'commission_minor' => $this->commissionMinor,
            'commission_currency' => $this->commissionCurrency,
            'status' => $this->status->value,
            'occurred_at' => $this->occurredAt?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
