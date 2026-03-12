<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $affiliate_attribution_id
 * @property string|null $affiliate_payout_id
 * @property string $affiliate_code
 * @property string|null $subject_type
 * @property string|null $subject_identifier
 * @property string|null $subject_instance
 * @property string|null $subject_title_snapshot
 * @property string|null $cart_identifier
 * @property string|null $cart_instance
 * @property string|null $voucher_code
 * @property string|null $external_reference
 * @property string|null $order_reference
 * @property string|null $conversion_type
 * @property int $subtotal_minor
 * @property int $value_minor
 * @property int $total_minor
 * @property int $commission_minor
 * @property string $commission_currency
 * @property ConversionStatus $status
 * @property string|null $channel
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $occurred_at
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read string|null $order_id Alias for order_reference
 * @property-read string $currency Alias for commission_currency
 * @property-read Affiliate $affiliate
 * @property-read AffiliateAttribution|null $attribution
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
        'subject_type',
        'subject_identifier',
        'subject_instance',
        'subject_title_snapshot',
        'cart_identifier',
        'cart_instance',
        'voucher_code',
        'external_reference',
        'order_reference',
        'conversion_type',
        'subtotal_minor',
        'value_minor',
        'total_minor',
        'commission_minor',
        'commission_currency',
        'status',
        'channel',
        'metadata',
        'owner_type',
        'owner_id',
        'occurred_at',
        'approved_at',
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
    }

    /**
     * Neutral alias for cart_identifier.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function subjectIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['subject_identifier'] ?? $this->attributes['cart_identifier'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Neutral alias for cart_instance.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function subjectInstance(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['subject_instance'] ?? $this->attributes['cart_instance'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Compatibility alias for subject_identifier.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function cartIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['cart_identifier'] ?? $this->attributes['subject_identifier'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Compatibility alias for subject_instance.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function cartInstance(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['cart_instance'] ?? $this->attributes['subject_instance'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Neutral alias for order_reference.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function externalReference(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['external_reference'] ?? $this->attributes['order_reference'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Alias for order_reference.
     *
     * @return Attribute<string|null, never>
     */
    protected function orderId(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['order_reference'] ?? $this->attributes['external_reference'] ?? null,
        );
    }

    /**
     * Compatibility alias for external_reference.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function orderReference(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['order_reference'] ?? $this->attributes['external_reference'] ?? null,
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Neutral alias for total_minor.
     *
     * @return Attribute<int, int>
     */
    protected function valueMinor(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) ($this->attributes['value_minor'] ?? $this->attributes['total_minor'] ?? 0),
            set: fn (mixed $value): int => max(0, (int) $value),
        );
    }

    /**
     * Compatibility alias for value_minor.
     *
     * @return Attribute<int, int>
     */
    protected function totalMinor(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) ($this->attributes['total_minor'] ?? $this->attributes['value_minor'] ?? 0),
            set: fn (mixed $value): int => max(0, (int) $value),
        );
    }

    /**
     * Alias for commission_currency.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->commission_currency,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'approved_at' => 'datetime',
            'value_minor' => 'integer',
            'status' => ConversionStatus::class,
        ];
    }
}
