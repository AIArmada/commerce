<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
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
 * @property string $commission_currency
 * @property float|null $commission_rate_to_base
 * @property string|null $commission_rate_base
 * @property ConversionStatus $status
 * @property string|null $network_link_id
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
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateAttribution|null $attribution
 * @property-read AffiliateLink|null $affiliateLink
 * @property-read AffiliatePayout|null $payout
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
        'commission_currency',
        'commission_rate_to_base',
        'commission_rate_base',
        'affiliate_link_id',
        'network_link_id',
        'sharer_user_id',
        'actor_user_id',
        'origin',
        'status',
        'channel',
        'metadata',
        'occurred_at',
        'approved_at',
        'rejected_at',
        'paid_at',
    ];

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
     * @return BelongsTo<AffiliateAttribution, $this>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AffiliateAttribution::class, 'affiliate_attribution_id');
    }

    /** @return BelongsTo<AffiliateLink, $this> */
    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class, 'affiliate_link_id');
    }

    /**
     * @return BelongsTo<AffiliatePayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
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
            if (! $conversion->isDirty('affiliate_payout_id') || $conversion->affiliate_payout_id === null) {
                return;
            }

            $payout = AffiliatePayout::query()->find($conversion->affiliate_payout_id);

            if (! $payout instanceof AffiliatePayout) {
                return;
            }

            $conversionCurrency = mb_strtoupper((string) $conversion->commission_currency);
            $payoutCurrency = mb_strtoupper((string) $payout->currency);

            if ($conversionCurrency !== '' && $conversionCurrency !== $payoutCurrency) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot link conversion [%s] (%s) to payout [%s] (%s): conversions must use the same currency as their payout.',
                    (string) $conversion->getKey(),
                    $conversionCurrency,
                    (string) $payout->getKey(),
                    $payoutCurrency,
                ));
            }
        });

        static::created(function (self $conversion): void {
            if (config('affiliates.commissions.auto_approve', false) && $conversion->status->equals(PendingConversion::class)) {
                $approvedAt = CarbonImmutable::now();

                $conversion->updateQuietly([
                    'status' => ApprovedConversion::class,
                    'approved_at' => $approvedAt,
                ]);

                $conversion->approved_at = $approvedAt;
            }
        });

        static::updated(function (self $conversion): void {
            if (! $conversion->wasChanged('status')) {
                return;
            }

            $newStatus = self::resolveStatus($conversion);

            if ($newStatus->equals(ApprovedConversion::class)) {
                if ($conversion->approved_at === null) {
                    $approvedAt = CarbonImmutable::now();

                    $conversion->updateQuietly(['approved_at' => $approvedAt]);
                    $conversion->approved_at = $approvedAt;
                }

                $conversion->rejected_at = null;

                return;
            }

            if ($newStatus->equals(RejectedConversion::class) && $conversion->rejected_at === null) {
                $conversion->updateQuietly(['rejected_at' => CarbonImmutable::now()]);
                $conversion->rejected_at = CarbonImmutable::now();

                return;
            }

            if ($newStatus->equals(PaidConversion::class) && $conversion->paid_at === null) {
                $conversion->updateQuietly(['paid_at' => CarbonImmutable::now()]);
                $conversion->paid_at = CarbonImmutable::now();
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
            'paid_at' => 'immutable_datetime',
            'value_minor' => 'integer',
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
