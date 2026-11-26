<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Models;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\Affiliates\Features\Conversions\States\PaidConversion;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Conversions\States\RejectedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ReversedConversion;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAccountingReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Contracts\ExchangeRateProvider;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $affiliate_attribution_id
 * @property string|null $affiliate_payout_id
 * @property string $affiliate_code
 * @property string|null $affiliate_program_id
 * @property string|null $subject_type
 * @property string|null $subject_key
 * @property string|null $subject_id
 * @property string|null $subject_instance
 * @property string|null $subject_title_snapshot
 * @property string|null $voucher_code
 * @property array<string, mixed>|null $commission_override
 * @property list<array<string, mixed>>|null $upline_levels
 * @property string|null $external_reference
 * @property string|null $idempotency_key
 * @property string|null $performance_bonus_key
 * @property string|null $conversion_type
 * @property int $subtotal_minor
 * @property int $value_minor
 * @property int $commission_minor
 * @property int $held_minor
 * @property string $commission_currency
 * @property float|null $commission_rate_to_base
 * @property string|null $commission_rate_base
 * @property ConversionStatus $status
 * @property string|null $source_ref
 * @property string|null $affiliate_link_id
 * @property string|null $sharer_user_id
 * @property string|null $actor_user_id
 * @property string|null $origin
 * @property string|null $channel
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $occurred_at
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $rejected_at
 * @property CarbonInterface|null $reversed_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
 */
class AffiliateConversion extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $table = 'affiliate_conversions';

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
        'affiliate_attribution_id',
        'affiliate_payout_id',
        'affiliate_program_id',
        'subject_type',
        'subject_key',
        'subject_id',
        'subject_instance',
        'subject_title_snapshot',
        'voucher_code',
        'commission_override',
        'upline_levels',
        'external_reference',
        'idempotency_key',
        'performance_bonus_key',
        'conversion_type',
        'subtotal_minor',
        'value_minor',
        'commission_minor',
        // held_minor is deliberately absent: it is an internal
        // accounting counter written only via forceFill by
        // ApplyConversionAccounting, never from input.
        'commission_currency',
        'commission_rate_to_base',
        'commission_rate_base',
        'affiliate_link_id',
        'source_ref',
        'sharer_user_id',
        'actor_user_id',
        'origin',
        'status',
        'channel',
        'metadata',
        'occurred_at',
        'approved_at',
        'rejected_at',
        'reversed_at',
        'paid_at',
    ];

    public function toData(): AffiliateConversionData
    {
        return new AffiliateConversionData(
            id: (string) $this->getKey(),
            affiliateId: (string) $this->affiliate_id,
            affiliateCode: (string) $this->affiliate_code,
            subjectType: $this->subject_type,
            subjectKey: $this->subject_key,
            subjectInstance: $this->subject_instance,
            subjectTitleSnapshot: $this->subject_title_snapshot,
            voucherCode: $this->voucher_code,
            affiliateProgramId: $this->affiliate_program_id,
            uplineLevels: $this->upline_levels,
            externalReference: $this->external_reference,
            subtotalMinor: (int) $this->subtotal_minor,
            valueMinor: (int) $this->value_minor,
            commissionMinor: (int) $this->commission_minor,
            commissionCurrency: (string) $this->commission_currency,
            conversionType: $this->conversion_type,
            status: $this->status->getValue(),
            occurredAt: $this->occurred_at,
            ownerType: $this->owner_type,
            ownerId: $this->owner_id,
            metadata: $this->metadata,
        );
    }

    public function getTable(): string
    {
        return config('affiliates.database.tables.conversions', parent::getTable());
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * Refuse to leave Approved while an open payout reserves this row.
     *
     * The payout lookup is unscoped by id and non-locking: scope
     * visibility must not change reservation facts, and completion
     * couples through the payout lock plus an Approved-only count
     * check, so a raced reversal fails closed instead of deadlocking.
     */
    public function assertNotReservedByOpenPayout(): void
    {
        if ($this->affiliate_payout_id === null) {
            return;
        }

        if (! app(PayoutAccountingReader::class)->isOpen(
            $this->affiliate_payout_id,
            spl_object_id($this->getConnection()),
        )) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Conversion [%s] is reserved by open payout [%s]; cancel or fail that payout first to release the funds.',
            (string) $this->getKey(),
            (string) $this->affiliate_payout_id,
        ));
    }

    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversion): void {
            self::stampConversionRate($conversion);

            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($conversion->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $conversion->owner_type = $owner->getMorphClass();
                $conversion->owner_id = $owner->getKey();
            }
        });

        static::saving(function (self $conversion): void {
            if (! $conversion->isDirty(['affiliate_payout_id', 'affiliate_id', 'commission_currency'])
                || $conversion->affiliate_payout_id === null) {
                return;
            }

            app(PayoutAccountingReader::class)->assertConversionLink(
                $conversion->affiliate_payout_id,
                (string) $conversion->getKey(),
                $conversion->exists ? (string) $conversion->getKey() : null,
                $conversion->affiliate_id,
                $conversion->commission_currency,
                spl_object_id($conversion->getConnection()),
            );
        });

        static::saving(function (self $conversion): void {
            if (! $conversion->exists && config('affiliates.commissions.auto_approve', false)
                && $conversion->status->equals(PendingConversion::class)) {
                $conversion->status = new ApprovedConversion($conversion);
            }

            if ($conversion->exists && ! $conversion->isDirty('status')) {
                return;
            }

            $status = self::resolveStatus($conversion);

            if ($status->equals(ApprovedConversion::class)) {
                $conversion->approved_at ??= CarbonImmutable::now();
                $conversion->rejected_at = null;
            } elseif ($status->equals(RejectedConversion::class)) {
                $conversion->rejected_at ??= CarbonImmutable::now();
            } elseif ($status->equals(PaidConversion::class)) {
                $conversion->paid_at ??= CarbonImmutable::now();
            } elseif ($status->equals(ReversedConversion::class)) {
                $conversion->reversed_at ??= CarbonImmutable::now();
            }
        });
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function externalReference(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['external_reference'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'commission_override' => 'array',
            'upline_levels' => 'array',
            'occurred_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'value_minor' => 'integer',
            'held_minor' => 'integer',
            'commission_rate_to_base' => 'float',
            'status' => ConversionStatus::class,
        ];
    }

    /**
     * Commission in the stamped base currency, or null when unconvertible.
     *
     * The stored rate wins so history never shifts; otherwise the rate
     * effective at occurred_at is used. Reporting only: never money movement.
     */
    public function baseCommissionMinor(): ?int
    {
        $minor = (int) ($this->commission_minor ?? 0);
        $currency = mb_strtoupper((string) ($this->commission_currency ?? ''));

        if ($this->commission_rate_to_base !== null && is_string($this->commission_rate_base)) {
            return (int) round($minor * (float) $this->commission_rate_to_base);
        }

        $base = app(ExchangeRateProvider::class)->baseCurrency();

        if ($currency === $base) {
            return $minor;
        }

        $rate = app(ExchangeRateProvider::class)->rate($currency, $base, $this->occurredAt());

        return $rate === null ? null : (int) round($minor * $rate);
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    private static function resolveStatus(self $conversion): ConversionStatus
    {
        return ConversionStatus::fromString($conversion->status, $conversion);
    }

    private static function stampConversionRate(self $conversion): void
    {
        if ($conversion->commission_rate_to_base !== null) {
            return;
        }

        $currency = mb_strtoupper((string) ($conversion->commission_currency ?? ''));

        if ($currency === '') {
            return;
        }

        $provider = app(ExchangeRateProvider::class);
        $base = $provider->baseCurrency();

        if ($currency === $base) {
            $conversion->commission_rate_to_base = 1.0;
            $conversion->commission_rate_base = $base;

            return;
        }

        $rate = $provider->rate($currency, $base, $conversion->occurredAt());

        if ($rate === null) {
            return;
        }

        $conversion->commission_rate_to_base = $rate;
        $conversion->commission_rate_base = $base;
    }

    private function occurredAt(): ?CarbonInterface
    {
        $occurred = $this->getAttribute('occurred_at');

        if ($occurred instanceof CarbonInterface) {
            return $occurred;
        }

        if (is_string($occurred) && $occurred !== '') {
            return CarbonImmutable::parse($occurred);
        }

        return null;
    }
}
