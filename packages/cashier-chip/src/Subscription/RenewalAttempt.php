<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Subscription;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $subscription_id
 * @property string $status
 * @property int $amount_minor
 * @property string|null $period_key
 * @property string|null $purchase_id
 * @property string|null $last_error_code
 * @property Carbon|null $lease_expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Subscription $subscription
 */
class RenewalAttempt extends Model
{
    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'cashier-chip.features.owner';

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty('amount_minor')) {
                throw new LogicException('A renewal attempt amount is frozen after it is claimed.');
            }
        });

        static::creating(function (self $attempt): void {
            if (! (bool) config('cashier-chip.features.owner.enabled', false)
                || ! (bool) config('cashier-chip.features.owner.auto_assign_on_create', true)) {
                return;
            }

            $subscriptionId = $attempt->getAttribute('subscription_id');

            if (! is_string($subscriptionId) || $subscriptionId === '') {
                return;
            }

            /** @var Subscription $subscription */
            $subscription = OwnerWriteGuard::findOrFailForOwner(
                Subscription::class,
                $subscriptionId,
                includeGlobal: (bool) config('cashier-chip.features.owner.include_global', false),
                message: 'Subscription is not accessible in the current owner scope.',
            );

            if (! $subscription->hasOwner()) {
                $attempt->removeOwner();

                return;
            }

            $attempt->owner_type = $subscription->owner_type;
            $attempt->owner_id = $subscription->owner_id;
        });
    }

    protected $fillable = [
        'subscription_id',
        'status',
        'amount_minor',
        'period_key',
        'purchase_id',
        'last_error_code',
        'lease_expires_at',
        'completed_at',
    ];

    public function getTable(): string
    {
        $tables = config('cashier-chip.database.tables', []);
        $prefix = config('cashier-chip.database.table_prefix', 'cashier_chip_');

        return $tables['renewal_attempts'] ?? $prefix . 'renewal_attempts';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, Model | string | null $owner = null, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return $query;
        }

        $owner ??= $this->resolveOwner();
        $includeGlobal ??= (bool) config('cashier-chip.features.owner.include_global', false);

        if (! is_string($owner)) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', self::class),
            );
        }

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    protected function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }
}
