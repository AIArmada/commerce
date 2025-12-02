<?php

declare(strict_types=1);

namespace AIArmada\Stock\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $stockable_type
 * @property string $stockable_id
 * @property string|null $user_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property int $quantity
 * @property string $type
 * @property string|null $reason
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read string|null $owner_display_name
 */
final class StockTransaction extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'user_id',
        'owner_type',
        'owner_id',
        'quantity',
        'type',
        'reason',
        'note',
        'transaction_date',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('stock.table_name', 'stock_transactions');
    }

    /**
     * Get the stockable model (Product, Variant, etc.)
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the owner model (polymorphic relationship).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the transaction.
     *
     * @return BelongsTo<\Illuminate\Foundation\Auth\User, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<\Illuminate\Foundation\Auth\User> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel);
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('stock.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    /**
     * Scope query to only global (ownerless) records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGlobalOnly(Builder $query): Builder
    {
        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    /**
     * Check if this model has an owner assigned.
     */
    public function hasOwner(): bool
    {
        return $this->owner_type !== null && $this->owner_id !== null;
    }

    /**
     * Check if this model is global (no owner).
     */
    public function isGlobal(): bool
    {
        return ! $this->hasOwner();
    }

    /**
     * Get the human-readable display name for the owner.
     */
    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        if (method_exists($owner, 'getAttribute')) {
            /** @var string|null $name */
            $name = $owner->getAttribute('name');
            /** @var string|null $displayName */
            $displayName = $owner->getAttribute('display_name');
            /** @var string|null $email */
            $email = $owner->getAttribute('email');
            /** @var int|string $key */
            $key = $owner->getKey();

            return $name ?? $displayName ?? $email ?? class_basename($owner).':'.(string) $key;
        }

        /** @var int|string $key */
        $key = $owner->getKey();

        return class_basename($owner).':'.(string) $key;
    }

    /**
     * Check if transaction is inbound.
     */
    public function isInbound(): bool
    {
        return $this->type === 'in';
    }

    /**
     * Check if transaction is outbound.
     */
    public function isOutbound(): bool
    {
        return $this->type === 'out';
    }

    /**
     * Check if transaction is a sale.
     */
    public function isSale(): bool
    {
        return $this->reason === 'sale';
    }

    /**
     * Check if transaction is an adjustment.
     */
    public function isAdjustment(): bool
    {
        return $this->reason === 'adjustment';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'quantity' => 'integer',
        ];
    }
}
