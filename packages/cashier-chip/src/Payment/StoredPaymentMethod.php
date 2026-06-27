<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Payment;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $billable_type
 * @property string $billable_id
 * @property string $recurring_token
 * @property string|null $type
 * @property string|null $brand
 * @property string|null $last_four
 * @property bool $is_default
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Model|null $billable
 */
final class StoredPaymentMethod extends Model
{
    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'cashier-chip.features.owner';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'billable_type',
        'billable_id',
        'recurring_token',
        'type',
        'brand',
        'last_four',
        'is_default',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('cashier-chip.database.tables', []);
        $prefix = config('cashier-chip.database.table_prefix', 'cashier_chip_');

        return $tables['payment_methods'] ?? $prefix . 'payment_methods';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return $query;
        }

        $owner ??= OwnerContext::resolve();
        $includeGlobal ??= (bool) config('cashier-chip.features.owner.include_global', false);

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $paymentMethod): void {
            $billable = $paymentMethod->billable;

            if ($billable instanceof Model) {
                $ownerType = $billable->getAttribute('owner_type');
                $ownerId = $billable->getAttribute('owner_id');

                if (is_string($ownerType) && $ownerType !== '' && is_scalar($ownerId)) {
                    $paymentMethod->owner_type = $ownerType;
                    $paymentMethod->owner_id = (string) $ownerId;

                    return;
                }
            }

            if ($paymentMethod->hasOwner()) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null) {
                $paymentMethod->assignOwner($owner);
            }
        });
    }
}
