<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * DTO representing a conversion + commission record.
 */
final readonly class AffiliateConversionData
{
    public string $status;

    public string $id;

    public string $affiliateId;

    public string $affiliateCode;

    public ?string $subjectType;

    public ?string $subjectKey;

    public ?string $subjectInstance;

    public ?string $subjectTitleSnapshot;

    public ?string $voucherCode;

    public ?string $affiliateProgramId;

    /** @var list<array<string, mixed>>|null */
    public ?array $uplineLevels;

    public ?string $externalReference;

    public ?string $conversionType;

    public int $subtotalMinor;

    public int $valueMinor;

    public int $commissionMinor;

    public string $commissionCurrency;

    public ?CarbonImmutable $occurredAt;

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
        ?string $voucherCode = null,
        ?string $affiliateProgramId = null,
        ?array $uplineLevels = null,
        ?string $externalReference = null,
        ?string $conversionType = null,
        int $subtotalMinor = 0,
        int $valueMinor = 0,
        int $commissionMinor = 0,
        string $commissionCurrency = 'MYR',
        string $status = 'pending',
        ?CarbonInterface $occurredAt = null,
        ?string $ownerType = null,
        string | int | null $ownerId = null,
        ?array $metadata = null,
        ?string $subjectType = null,
        ?string $subjectTitleSnapshot = null,
    ) {
        $this->id = $id;
        $this->affiliateId = $affiliateId;
        $this->affiliateCode = $affiliateCode;
        $this->subjectType = $subjectType;
        $this->subjectKey = $subjectKey;
        $this->subjectInstance = $subjectInstance;
        $this->subjectTitleSnapshot = $subjectTitleSnapshot;
        $this->voucherCode = $voucherCode;
        $this->affiliateProgramId = $affiliateProgramId;
        $this->uplineLevels = $uplineLevels;
        $this->externalReference = $externalReference;
        $this->conversionType = $conversionType;
        $this->subtotalMinor = $subtotalMinor;
        $this->valueMinor = $valueMinor;
        $this->commissionMinor = $commissionMinor;
        $this->commissionCurrency = $commissionCurrency;
        $this->status = $status;
        $this->occurredAt = $occurredAt === null ? null : CarbonImmutable::instance($occurredAt);
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->metadata = $metadata;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'id' => $this->id,
            'affiliate_id' => $this->affiliateId,
            'affiliate_code' => $this->affiliateCode,
            'subject_type' => $this->subjectType,
            'subject_key' => $this->subjectKey,
            'subject_instance' => $this->subjectInstance,
            'subject_title_snapshot' => $this->subjectTitleSnapshot,
            'voucher_code' => $this->voucherCode,
            'affiliate_program_id' => $this->affiliateProgramId,
            'upline_levels' => $this->uplineLevels,
            'external_reference' => $this->externalReference,
            'conversion_type' => $this->conversionType,
            'subtotal_minor' => $this->subtotalMinor,
            'value_minor' => $this->valueMinor,
            'commission_minor' => $this->commissionMinor,
            'commission_currency' => $this->commissionCurrency,
            'occurred_at' => $this->occurredAt?->format('c'),
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'metadata' => $this->metadata,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getFormattedValue(): string
    {
        return MoneyFormatter::formatMinorWithCode($this->valueMinor, $this->commissionCurrency);
    }

    public function getFormattedCommission(): string
    {
        return MoneyFormatter::formatMinorWithCode($this->commissionMinor, $this->commissionCurrency);
    }
}
