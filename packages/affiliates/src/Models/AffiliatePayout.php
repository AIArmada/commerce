<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $reference
 * @property string|null $affiliate_payout_operation_id
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
 * @property CarbonInterface|null $failed_at
 * @property CarbonInterface|null $cancelled_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property string|null $external_reference
 * @property-read Model|null $payee
 * @property-read Model|null $owner
 * @property-read Collection<int, AffiliateConversion> $conversions
 * @property-read Collection<int, AffiliatePayoutEvent> $events
 */
class AffiliatePayout extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'reference',
        'affiliate_payout_operation_id',
        'status',
        'total_minor',
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
        'failed_at',
        'cancelled_at',
    ];

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }

    protected $casts = [
        'status' => PayoutStatus::class,
        'metadata' => 'array',
        'scheduled_at' => 'immutable_datetime',
        'paid_at' => 'immutable_datetime',
        'failed_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.payouts', parent::getTable());
    }

    /**
     * Polymorphic payee (typically an Affiliate).
     *
     * @return MorphTo<Model, $this>
     */
    public function payee(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<AffiliateConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }

    /**
     * @return HasMany<AffiliatePayoutEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AffiliatePayoutEvent::class, 'affiliate_payout_id')->latest();
    }

    /** @return BelongsTo<AffiliatePayoutOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayoutOperation::class, 'affiliate_payout_operation_id');
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

    public function isFailed(): bool
    {
        return $this->failed_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
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
}
