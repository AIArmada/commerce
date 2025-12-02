<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string|null $owner_type
 * @property string|null $owner_id
 *
 * @method static Builder<static> forOwner(?Model $owner = null, bool $includeGlobal = true)
 */
abstract class ChipModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    abstract protected static function tableSuffix(): string;

    #[Override]
    final public function getTable(): string
    {
        $prefix = (string) config('chip.database.table_prefix', 'chip_');

        return $prefix.static::tableSuffix();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    final public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = true): Builder
    {
        if (! config('chip.owner.enabled', false)) {
            return $query;
        }

        $owner ??= $this->resolveOwner();

        if ($owner === null) {
            return $includeGlobal ? $query->whereNull('owner_type') : $query;
        }

        if ($includeGlobal) {
            return $query->where(function (Builder $q) use ($owner): void {
                $q->where(function (Builder $subQ) use ($owner): void {
                    $subQ->where('owner_type', $owner->getMorphClass())
                        ->where('owner_id', $owner->getKey());
                })->orWhereNull('owner_type');
            });
        }

        return $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    final public function hasOwner(): bool
    {
        return $this->owner_type !== null && $this->owner_id !== null;
    }

    final public function isGlobal(): bool
    {
        return ! $this->hasOwner();
    }

    final public function assignOwner(Model $owner): static
    {
        $this->owner_type = $owner->getMorphClass();
        $this->owner_id = (string) $owner->getKey();

        return $this;
    }

    final public function removeOwner(): static
    {
        $this->owner_type = null;
        $this->owner_id = null;

        return $this;
    }

    protected function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        return app(OwnerResolverInterface::class)->resolve();
    }

    protected function toTimestamp(?int $value): ?Carbon
    {
        return $value !== null ? Carbon::createFromTimestampUTC($value) : null;
    }

    /**
     * Convert an amount in cents to a Money object.
     *
     * @param  int|null  $amount  Amount in cents (smallest currency unit)
     * @param  string  $currency  ISO 4217 currency code (default: MYR)
     */
    protected function toMoney(?int $amount, string $currency = 'MYR'): ?Money
    {
        if ($amount === null) {
            return null;
        }

        return Money::{$currency}($amount);
    }
}
