<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $reference
 * @property PayoutStatus $status
 * @property int $total_minor
 * @property int $conversion_count
 * @property string $currency
 * @property string|null $payee_type
 * @property string|null $payee_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $scheduled_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read int $amount_minor Alias for total_minor
 * @property-read string|null $external_reference From metadata
 * @property-read string|null $notes From metadata
 * @property-read Affiliate|null $affiliate Alias for payee when payee is an Affiliate
 * @property-read Model|null $payee
 * @property-read Model|null $owner
 * @property-read Collection<int, AffiliateConversion> $conversions
 * @property-read Collection<int, AffiliatePayoutEvent> $events
 */
class AffiliatePayout extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'reference',
        'status',
        'total_minor',
        'amount_minor',
        'conversion_count',
        'currency',
        'metadata',
        'external_reference',
        'payee_type',
        'payee_id',
        'owner_type',
        'owner_id',
        'scheduled_at',
        'paid_at',
    ];

    protected $casts = [
        'status' => PayoutStatus::class,
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.payouts', parent::getTable());
    }

    /**
     * Polymorphic payee (typically an Affiliate).
     *
     * @return MorphTo<Model, self>
     */
    public function payee(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Alias relation for payee when it is an Affiliate.
     *
     * @return MorphTo<Affiliate, self>
     */
    public function affiliate(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'payee_type', 'payee_id');
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }

    /**
     * @return HasMany<AffiliatePayoutEvent, self>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AffiliatePayoutEvent::class, 'affiliate_payout_id')->latest();
    }

    protected static function booted(): void
    {
        static::creating(function (self $payout): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($payout->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $payout->owner_type = $owner->getMorphClass();
                $payout->owner_id = $owner->getKey();
            }
        });

        static::deleting(function (self $payout): void {
            $payout->events()->delete();
            $payout->conversions()->update(['affiliate_payout_id' => null]);
        });
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

    /**
     * Alias for total_minor (for code compatibility).
     *
     * @return Attribute<int, never>
     */
    protected function amountMinor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_minor,
            set: fn ($value) => [
                'total_minor' => max(0, (int) $value),
            ],
        );
    }

    /**
     * Get external reference from metadata.
     *
     * @return Attribute<string|null, never>
     */
    protected function externalReference(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['external_reference'] ?? null,
            set: function ($value, array $attributes): array {
                $metadata = $attributes['metadata'] ?? null;

                if (is_string($metadata)) {
                    $decoded = json_decode($metadata, true);
                    $metadata = is_array($decoded) ? $decoded : [];
                }

                $metadata = is_array($metadata) ? $metadata : [];

                if ($value === null || $value === '') {
                    unset($metadata['external_reference']);
                } else {
                    $metadata['external_reference'] = (string) $value;
                }

                return ['metadata' => $metadata === [] ? null : $this->asJson($metadata)];
            },
        );
    }

    /**
     * Get notes from metadata.
     *
     * @return Attribute<string|null, never>
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['notes'] ?? null,
        );
    }
}
