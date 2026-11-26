<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Models;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutEventHistory;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReservationReader;
use AIArmada\Affiliates\Features\Payouts\States\PayoutStatus;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
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

    protected $table = 'affiliate_payouts';

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
     * Fail loud when linked conversions span currencies.
     *
     * Each payout reserves exactly one per-currency balance, so mixed legs
     * are corrupt data: actions check before attaching, this guard catches
     * every other write path (console, Filament, raw queries replayed
     * through the model).
     */
    public function assertHomogeneousConversions(): void
    {
        $expected = mb_strtoupper((string) $this->currency);

        $currencies = app(PayoutConversionIntegrity::class)->currencies(
            (string) $this->getKey(),
            spl_object_id($this->getConnection()),
        );
        $foreign = array_filter($currencies, static fn (string $code): bool => $code !== $expected);

        if ($foreign !== []) {
            throw new InvalidArgumentException(sprintf(
                'Payout [%s] is %s but links %s conversions: a payout must use the same currency as its conversions.',
                (string) $this->getKey(),
                $expected,
                implode(',', $foreign),
            ));
        }
    }

    /**
     * Fail loud when linked conversions do not belong to this payout's affiliate.
     */
    public function assertSingleAffiliateConversions(
        ?string $candidateAffiliateId = null,
        ?string $existingConversionId = null,
        Model | string | null $owner = OwnerContext::CURRENT,
    ): void {
        $affiliateIds = collect(app(PayoutConversionIntegrity::class)->affiliateIds(
            (string) $this->getKey(),
            spl_object_id($this->getConnection()),
            $existingConversionId,
        ));

        if ($candidateAffiliateId !== null) {
            $affiliateIds->push($candidateAffiliateId);
        }

        $expectedAffiliateId = null;

        if ($this->affiliate_payout_operation_id !== null) {
            $scope = $owner === OwnerContext::CURRENT ? OwnerContext::resolveConfigured(static::ownerScopeConfig()) : $owner;
            $expectedAffiliateId = app(PayoutReservationReader::class)->affiliateIdForOperation(
                $this->affiliate_payout_operation_id,
                $scope instanceof Model ? $scope->getMorphClass() : null,
                $scope instanceof Model ? (string) $scope->getKey() : null,
                spl_object_id($this->getConnection()),
            );

            if ($expectedAffiliateId === null) {
                throw new InvalidArgumentException(sprintf(
                    'Payout [%s] has no operation in the requested owner scope.',
                    (string) $this->getKey(),
                ));
            }
        }

        if ($expectedAffiliateId !== null) {
            $payeeType = $this->payee_type === null ? null : (Relation::getMorphedModel($this->payee_type) ?? $this->payee_type);

            if ($payeeType === null || ! is_a($payeeType, Affiliate::class, true) || $this->payee_id !== $expectedAffiliateId) {
                throw new InvalidArgumentException('Payout payee does not match its reservation affiliate.');
            }
        }

        if ($expectedAffiliateId === null && $this->payee_type !== null && $this->payee_id !== null) {
            $payeeType = Relation::getMorphedModel($this->payee_type) ?? $this->payee_type;

            if (is_a($payeeType, Affiliate::class, true)) {
                $expectedAffiliateId = Affiliate::query()->forOwner($owner)->whereKey($this->payee_id)->value('id');
            }
        }

        if ($expectedAffiliateId !== null) {
            $affiliateIds->push($expectedAffiliateId);
        }

        $affiliateIds = $affiliateIds
            ->map(static fn (mixed $affiliateId): string => (string) $affiliateId)
            ->filter(static fn (string $affiliateId): bool => $affiliateId !== '')
            ->unique();

        if ($affiliateIds->count() > 1) {
            throw new InvalidArgumentException(sprintf(
                'Payout [%s] links conversions across %d affiliates; a payout must belong to one affiliate.',
                (string) $this->getKey(),
                $affiliateIds->count(),
            ));
        }
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
            if (! $payout->status->equals(PendingPayout::class)
                || (int) $payout->total_minor !== 0
                || (int) $payout->conversion_count !== 0
                || $payout->external_reference !== null
                || $payout->affiliate_payout_operation_id !== null
                || app(PayoutReservationReader::class)->hasOperations((string) $payout->getKey(), spl_object_id($payout->getConnection()))
                || app(PayoutConversionIntegrity::class)->hasConversions((string) $payout->getKey(), spl_object_id($payout->getConnection()))
                || app(PayoutEventHistory::class)->hasEvents((string) $payout->getKey())) {
                throw new InvalidArgumentException('A payout with financial, processing or event history cannot be deleted directly.');
            }
        });

        static::saving(function (self $payout): void {
            if (! $payout->exists || ! $payout->isDirty('currency')) {
                return;
            }

            $payout->assertHomogeneousConversions();
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
