<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Models\AffiliateAttribution;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Lightweight DTO describing an attribution row.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class AffiliateAttributionData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $affiliateId,
        public readonly string $affiliateCode,
        public readonly ?string $cartIdentifier = null,
        public readonly string $cartInstance = 'default',
        public readonly ?string $cookieValue = null,
        public readonly ?string $voucherCode = null,
        public readonly ?string $source = null,
        public readonly ?string $medium = null,
        public readonly ?string $campaign = null,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?CarbonInterface $expiresAt = null,
        public readonly ?array $metadata = null,
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

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->isPast();
    }

    public function hasUtmParameters(): bool
    {
        return $this->source !== null || $this->medium !== null || $this->campaign !== null;
    }

    public function getUtmString(): ?string
    {
        if (! $this->hasUtmParameters()) {
            return null;
        }

        $parts = array_filter([
            $this->source ? "utm_source={$this->source}" : null,
            $this->medium ? "utm_medium={$this->medium}" : null,
            $this->campaign ? "utm_campaign={$this->campaign}" : null,
        ]);

        return implode('&', $parts);
    }
}
