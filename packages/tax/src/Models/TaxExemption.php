<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Tax\Actions\Exemption\ApproveExemptionAction;
use AIArmada\Tax\Actions\Exemption\ExpireExemptionAction;
use AIArmada\Tax\Actions\Exemption\MarkExemptionUnderReviewAction;
use AIArmada\Tax\Actions\Exemption\RejectExemptionAction;
use AIArmada\Tax\Actions\Exemption\RevokeExemptionAction;
use AIArmada\Tax\Database\Factories\TaxExemptionFactory;
use AIArmada\Tax\Queries\TaxOwnerScope;
use AIArmada\Tax\States\TaxExemptionState\ApprovedState;
use AIArmada\Tax\States\TaxExemptionState\PendingState;
use AIArmada\Tax\States\TaxExemptionState\RejectedState;
use AIArmada\Tax\States\TaxExemptionState\TaxExemptionState;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\ModelStates\HasStates;

/**
 * Represents a tax exemption for a customer or entity.
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $exemptable_id
 * @property string|null $exemptable_type
 * @property string|null $tax_zone_id
 * @property string $reason
 * @property string|null $certificate_number
 * @property string|null $document_path
 * @property TaxExemptionState $status
 * @property string|null $rejection_reason
 * @property CarbonInterface|null $verified_at
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $revoked_at
 * @property-read TaxZone|null $taxZone
 * @property-read Model|null $exemptable
 */
class TaxExemption extends Model implements Auditable
{
    use HasCommerceAudit;

    /** @use HasFactory<TaxExemptionFactory> */
    use HasFactory;

    use HasOwner;
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'tax.features.owner';

    use LogsCommerceActivity;

    protected $fillable = [
        'exemptable_id',
        'exemptable_type',
        'tax_zone_id',
        'reason',
        'certificate_number',
        'document_path',
        'status',
        'rejection_reason',
        'verified_at',
        'starts_at',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [];

    protected function casts(): array
    {
        return [
            'status' => TaxExemptionState::class,
            'verified_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $exemption): void {
            if ($exemption->starts_at !== null && $exemption->expires_at !== null && $exemption->expires_at->lessThanOrEqualTo($exemption->starts_at)) {
                throw ValidationException::withMessages([
                    'expires_at' => 'The exemption expiry must be after its start date.',
                ]);
            }

            if ($exemption->tax_zone_id !== null && ($exemption->isDirty('tax_zone_id') || ! $exemption->exists)) {
                $zoneExists = TaxOwnerScope::apply(TaxZone::query(), [])
                    ->whereKey($exemption->tax_zone_id)
                    ->lockForUpdate()
                    ->first() !== null;

                if (! $zoneExists) {
                    throw new AuthorizationException('Tax zone is not accessible in the current owner scope.');
                }
            }

            if (! config('tax.features.owner.enabled', false)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner === null) {
                if ($exemption->owner_type !== null || $exemption->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned tax exemptions without an owner context.');
                }

                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            } elseif ($exemption->owner_type === null && $exemption->owner_id === null) {
                if ($exemption->exists) {
                    throw new AuthorizationException('Cannot mutate global tax exemptions without explicit global context.');
                }

                if ((bool) config('tax.features.owner.auto_assign_on_create', true)) {
                    $exemption->assignOwner($owner);
                }
            }

            if ($owner !== null && ! $exemption->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write tax exemptions outside the current owner scope.');
            }

            $exemptableClass = is_string($exemption->exemptable_type)
                ? (Relation::getMorphedModel($exemption->exemptable_type) ?? $exemption->exemptable_type)
                : null;

            if (
                $exemption->exemptable_id !== null
                && is_string($exemption->exemptable_type)
                && $exemption->exemptable_type !== ''
                && (
                    $exemption->isDirty('exemptable_type')
                    || $exemption->isDirty('exemptable_id')
                    || ! $exemption->exists
                )
                && $exemptableClass !== null
                && is_a($exemptableClass, Model::class, true)
                && in_array(HasOwner::class, class_uses_recursive($exemptableClass), true)
                && (! method_exists($exemptableClass, 'ownerScopeConfig') || $exemptableClass::ownerScopeConfig()->enabled)
            ) {
                OwnerWriteGuard::findOrFailForOwner(
                    modelClass: $exemptableClass,
                    id: $exemption->exemptable_id,
                    owner: $owner,
                    includeGlobal: (bool) config('tax.features.owner.include_global', false),
                    message: 'Exemptable entity is not accessible in the current owner scope.',
                );
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaxExemptionFactory
    {
        return TaxExemptionFactory::new();
    }

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_exemptions', 'tax_exemptions');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The exemptable entity (Customer, User, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function exemptable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The tax zone this exemption applies to (null = all zones).
     *
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'tax_zone_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = CarbonImmutable::now();

        return $query->where('status', ApprovedState::$name)
            ->where(function ($q) use ($now): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PendingState::$name);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ApprovedState::$name);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', RejectedState::$name);
    }

    /**
     * Scope to exemptions for a specific zone (or all zones).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForZone(Builder $query, ?string $zoneId): Builder
    {
        return $query->where(function (Builder $builder) use ($zoneId): void {
            $builder->whereNull('tax_zone_id');

            if ($zoneId !== null) {
                $builder->orWhere('tax_zone_id', $zoneId);
            }
        });
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if (! $this->status instanceof ApprovedState) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < $now) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < CarbonImmutable::now();
    }

    public function isPending(): bool
    {
        return $this->status instanceof PendingState;
    }

    public function isApproved(): bool
    {
        return $this->status instanceof ApprovedState;
    }

    public function isRejected(): bool
    {
        return $this->status instanceof RejectedState;
    }

    /**
     * Check if exemption applies to a specific zone.
     */
    public function appliesToZone(?string $zoneId): bool
    {
        // If no zone specified on exemption, it applies to all
        if ($this->tax_zone_id === null) {
            return true;
        }

        return $this->tax_zone_id === $zoneId;
    }

    public function approve(): self
    {
        return app(ApproveExemptionAction::class)->execute($this);
    }

    public function reject(string $reason): self
    {
        return app(RejectExemptionAction::class)->execute($this, $reason);
    }

    public function markUnderReview(): self
    {
        return app(MarkExemptionUnderReviewAction::class)->execute($this);
    }

    public function revoke(): self
    {
        return app(RevokeExemptionAction::class)->execute($this);
    }

    public function expire(): self
    {
        return app(ExpireExemptionAction::class)->execute($this);
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reason', 'status', 'verified_at', 'starts_at', 'expires_at'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
