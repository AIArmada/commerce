<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Models\AffiliateAttribution;
use DateTimeInterface;

/**
 * Lightweight DTO describing an attribution row.
 */
readonly class AffiliateAttributionData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $affiliateCode,
        public ?string $cartIdentifier,
        public string $cartInstance,
        public ?string $cookieValue,
        public ?string $voucherCode,
        public ?string $source,
        public ?string $medium,
        public ?string $campaign,
        public ?DateTimeInterface $expiresAt,
        public ?array $metadata,
    ) {}

    public static function fromModel(AffiliateAttribution $attribution): self
    {
        return new self(
            id: (string) $attribution->getKey(),
            affiliateId: (string) $attribution->affiliate_id,
            affiliateCode: (string) $attribution->affiliate_code,
            cartIdentifier: $attribution->cart_identifier,
            cartInstance: (string) $attribution->cart_instance,
            cookieValue: $attribution->cookie_value,
            voucherCode: $attribution->voucher_code,
            source: $attribution->source,
            medium: $attribution->medium,
            campaign: $attribution->campaign,
            expiresAt: $attribution->expires_at,
            metadata: $attribution->metadata,
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
            'cookie_value' => $this->cookieValue,
            'voucher_code' => $this->voucherCode,
            'source' => $this->source,
            'medium' => $this->medium,
            'campaign' => $this->campaign,
            'expires_at' => $this->expiresAt?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
