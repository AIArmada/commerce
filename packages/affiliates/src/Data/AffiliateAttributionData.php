<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lightweight DTO describing an attribution row.
 */
final readonly class AffiliateAttributionData
{
    public string $id;

    public string $affiliateId;

    public string $affiliateCode;

    public ?string $subjectType;

    public ?string $subjectKey;

    public string $subjectInstance;

    public ?string $subjectTitleSnapshot;

    public ?string $cartIdentifier;

    public string $cartInstance;

    public ?string $cookieValue;

    public ?string $voucherCode;

    public ?string $affiliateProgramId;

    /** @var array<string, mixed>|null */
    public ?array $commissionOverride;

    /** @var list<array<string, mixed>>|null */
    public ?array $uplineLevels;

    public ?string $source;

    public ?string $medium;

    public ?string $campaign;

    public ?CarbonImmutable $expiresAt;

    public ?string $ownerType;

    public string | int | null $ownerId;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $metadata;

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        string $id,
        string $affiliateId,
        string $affiliateCode,
        ?string $subjectKey = null,
        ?string $subjectInstance = null,
        ?string $cartIdentifier = null,
        string $cartInstance = 'default',
        ?string $cookieValue = null,
        ?string $voucherCode = null,
        ?string $affiliateProgramId = null,
        ?array $commissionOverride = null,
        ?array $uplineLevels = null,
        ?string $source = null,
        ?string $medium = null,
        ?string $campaign = null,
        ?CarbonInterface $expiresAt = null,
        ?string $ownerType = null,
        string | int | null $ownerId = null,
        ?array $metadata = null,
        ?string $subjectType = null,
        ?string $subjectTitleSnapshot = null,
    ) {
        $resolvedSubjectIdentifier = $subjectKey ?? $cartIdentifier;
        $resolvedSubjectInstance = $subjectInstance ?? $cartInstance;

        $this->id = $id;
        $this->affiliateId = $affiliateId;
        $this->affiliateCode = $affiliateCode;
        $this->subjectType = $subjectType;
        $this->subjectKey = $resolvedSubjectIdentifier;
        $this->subjectInstance = $resolvedSubjectInstance;
        $this->subjectTitleSnapshot = $subjectTitleSnapshot;
        $this->cartIdentifier = $cartIdentifier ?? $resolvedSubjectIdentifier;
        $this->cartInstance = $cartInstance !== 'default' || $subjectInstance === null ? $cartInstance : $resolvedSubjectInstance;
        $this->cookieValue = $cookieValue;
        $this->voucherCode = $voucherCode;
        $this->affiliateProgramId = $affiliateProgramId;
        $this->commissionOverride = $commissionOverride;
        $this->uplineLevels = $uplineLevels;
        $this->source = $source;
        $this->medium = $medium;
        $this->campaign = $campaign;
        $this->expiresAt = $expiresAt === null ? null : CarbonImmutable::instance($expiresAt);
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->metadata = $metadata;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'affiliate_id' => $this->affiliateId,
            'affiliate_code' => $this->affiliateCode,
            'subject_type' => $this->subjectType,
            'subject_key' => $this->subjectKey,
            'subject_instance' => $this->subjectInstance,
            'subject_title_snapshot' => $this->subjectTitleSnapshot,
            'cart_identifier' => $this->cartIdentifier,
            'cart_instance' => $this->cartInstance,
            'cookie_value' => $this->cookieValue,
            'voucher_code' => $this->voucherCode,
            'affiliate_program_id' => $this->affiliateProgramId,
            'commission_override' => $this->commissionOverride,
            'upline_levels' => $this->uplineLevels,
            'source' => $this->source,
            'medium' => $this->medium,
            'campaign' => $this->campaign,
            'expires_at' => $this->expiresAt?->format('c'),
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'metadata' => $this->metadata,
        ];
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

        return http_build_query(array_filter([
            'utm_source' => $this->source,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->campaign,
        ], static fn (?string $value): bool => $value !== null), '', '&', PHP_QUERY_RFC3986);
    }
}
