<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Products\Concerns\IsCatalogEntity;
use AIArmada\Products\Support\ChildOwnershipGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $product_id
 * @property string $name
 * @property string|null $display_name
 * @property int $position
 * @property string $visibility
 * @property CarbonImmutable|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Collection<int, OptionValue> $values
 */
class Option extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use IsCatalogEntity;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'product_id',
        'name',
        'display_name',
        'position',
        'visibility',
        'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'visibility' => 'string',
            'hidden_at' => 'immutable_datetime',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'visibility' => 'visible',
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['options'] ?? $prefix . 'options';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('products.features.owner.include_global', false);
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the option values.
     *
     * @return HasMany<OptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class, 'option_id')->orderBy('position');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visibility', 'visible');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (Option $option): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                if (! Product::query()->whereKey($option->product_id)->exists()) {
                    throw new InvalidArgumentException('Invalid product_id: product not found.');
                }

                return;
            }

            $includeGlobal = (bool) config('products.features.owner.include_global', false);

            $product = OwnerWriteGuard::findOrFailForOwner(
                Product::class,
                $option->product_id,
                includeGlobal: $includeGlobal,
                message: 'Cross-tenant write blocked: option product is not accessible in the current owner context.',
            );

            ChildOwnershipGuard::apply($option, $product, 'product');
        });

        static::updating(function (Option $option): void {
            // Parent and tenancy are immutable after creation.
            if ($option->isDirty('product_id') || $option->isDirty('owner_type') || $option->isDirty('owner_id')) {
                throw new InvalidArgumentException('Cross-tenant write blocked: option product and owner are immutable after creation.');
            }

            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $currentOwner = OwnerContext::resolve();

            // No owner context = system/admin operation, allow it
            if ($currentOwner === null) {
                return;
            }

            $optionIsGlobal = $option->owner_type === null && $option->owner_id === null;

            // Block tenant from updating global options
            if ($optionIsGlobal) {
                throw new InvalidArgumentException('Cross-tenant write blocked: cannot update global option from an owner context.');
            }

            // Block cross-tenant updates
            if (! $option->belongsToOwner($currentOwner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: option does not belong to the current owner context.');
            }
        });

        static::saving(function (Option $option): void {
            if ($option->isDirty('visibility')) {
                $option->hidden_at = $option->visibility === 'hidden' ? CarbonImmutable::now() : null;
            }
        });

        static::deleting(function (Option $option): void {
            // Delete values individually so OptionValue::deleting detaches
            // variant pivots instead of leaving orphaned pivot rows.
            $option->values()->reorder()->chunkById(100, function ($values): void {
                $values->each(fn ($value) => $value->delete());
            });
        });
    }
}
