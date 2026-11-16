<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\Products\Support\CompositeBuyableRegistry;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Products\Database\Factories\ProductComponentFactory;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Services\CompositePackageValidator;
use AIArmada\Products\Support\ChildOwnershipGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $product_id
 * @property string $component_product_id
 * @property string|null $variant_id
 * @property int $quantity
 * @property int $position
 * @property int|null $unit_price
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Product $componentProduct
 * @property-read Variant|null $variant
 */
class ProductComponent extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'product_id',
        'component_product_id',
        'buyable_type',
        'buyable_id',
        'variant_id',
        'quantity',
        'position',
        'unit_price',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'position' => 'integer',
            'unit_price' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['product_components'] ?? $prefix . 'components';
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /**
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    /** @return MorphTo<Model, $this> */
    public function componentBuyable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'buyable_type', 'buyable_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (ProductComponent $component): void {
            [$parent, $componentProduct, $variant, $buyable] = self::resolveComposition($component);

            $validator = app(CompositePackageValidator::class);

            if ($componentProduct instanceof Product) {
                if ($parent->status === ProductStatus::Active) {
                    $validator->assertPublishableComponent($parent, $componentProduct, $variant, (int) $component->quantity);
                } else {
                    $validator->assertComponent($parent, $componentProduct, $variant, (int) $component->quantity);
                }
            } else {
                if (! $buyable instanceof Model || ! $buyable instanceof Buyable) {
                    throw new InvalidCompositePackageException('Composite Buyable could not be resolved.');
                }
                $validator->assertBuyableComponent(
                    $parent, $buyable, (int) $component->quantity,
                    $parent->status === ProductStatus::Active,
                );
            }

            self::assertComponentPricing($component);
            ChildOwnershipGuard::apply($component, $parent, 'product');
        });

        static::updating(function (ProductComponent $component): void {
            if ($component->isDirty('product_id')
                || $component->isDirty('component_product_id')
                || $component->isDirty('variant_id')
                || $component->isDirty('buyable_type')
                || $component->isDirty('buyable_id')
                || $component->isDirty('owner_type')
                || $component->isDirty('owner_id')) {
                throw new InvalidArgumentException('Composite component identity and owner are immutable after creation.');
            }

            [$parent, $componentProduct, $variant] = self::resolveComposition($component);

            $validator = app(CompositePackageValidator::class);

            if ($componentProduct instanceof Product) {
                if ($parent->status === ProductStatus::Active) {
                    $validator->assertPublishableComponent($parent, $componentProduct, $variant, (int) $component->quantity);
                } else {
                    $validator->assertComponent($parent, $componentProduct, $variant, (int) $component->quantity);
                }
            } else {
                if (! $buyable instanceof Model || ! $buyable instanceof Buyable) {
                    throw new InvalidCompositePackageException('Composite Buyable could not be resolved.');
                }
                $validator->assertBuyableComponent(
                    $parent, $buyable, (int) $component->quantity,
                    $parent->status === ProductStatus::Active,
                );
            }

            self::assertComponentPricing($component);
        });

        static::saved(static function (ProductComponent $component): void {
            self::syncParentShipping($component);
        });

        static::deleted(static function (ProductComponent $component): void {
            self::syncParentShipping($component);
        });

        static::deleting(function (ProductComponent $component): void {
            $parent = Product::query()->whereKey($component->product_id)->first();

            if (! $parent instanceof Product || $parent->status !== ProductStatus::Active) {
                return;
            }

            $remaining = ProductComponent::query()
                ->where('product_id', $component->product_id)
                ->whereKeyNot($component->getKey())
                ->count();

            if ($remaining === 0) {
                throw new InvalidCompositePackageException('An active composite package must keep at least one component.');
            }
        });
    }

    /**
     * @return array{0: Product, 1: Product|null, 2: Variant|null, 3: Buyable|null}
     */
    private static function resolveComposition(ProductComponent $component): array
    {
        $isBuyable = $component->buyable_type !== null || $component->buyable_id !== null;
        if ($isBuyable === ($component->component_product_id !== null)) {
            throw new InvalidCompositePackageException('A component must reference exactly one Product or registered Buyable.');
        }
        if ($isBuyable && $component->variant_id !== null) {
            throw new InvalidCompositePackageException('External Buyable components cannot specify Product variants.');
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        if (! (bool) config('products.features.owner.enabled', true)) {
            $parent = Product::query()->whereKey($component->product_id)->first();
            $componentProduct = $isBuyable ? null : Product::query()->whereKey($component->component_product_id)->first();
            $variant = $component->variant_id !== null
                ? Variant::query()->whereKey($component->variant_id)->first()
                : null;
        } else {
            $parent = OwnerWriteGuard::findOrFailForOwner(
                Product::class,
                $component->product_id,
                includeGlobal: $includeGlobal,
                message: 'Cross-tenant write blocked: composite parent is not accessible in the current owner context.',
            );

            $componentProduct = $isBuyable ? null : OwnerWriteGuard::findOrFailForOwner(
                Product::class,
                $component->component_product_id,
                includeGlobal: $includeGlobal,
                message: 'Cross-tenant write blocked: component product is not accessible in the current owner context.',
            );

            $variant = $component->variant_id !== null
                ? OwnerWriteGuard::findOrFailForOwner(
                    Variant::class,
                    $component->variant_id,
                    includeGlobal: $includeGlobal,
                    message: 'Cross-tenant write blocked: component variant is not accessible in the current owner context.',
                )
                : null;
        }

        if (! $parent instanceof Product || (! $isBuyable && ! $componentProduct instanceof Product)) {
            throw new InvalidCompositePackageException('Composite parent and component must both exist.');
        }

        if ($variant !== null && ! $variant instanceof Variant) {
            throw new InvalidCompositePackageException('Composite component variant could not be resolved.');
        }

        $buyable = $isBuyable
            ? app(CompositeBuyableRegistry::class)->resolve($parent, (string) $component->buyable_type, (string) $component->buyable_id)
            : null;

        return [$parent, $componentProduct, $variant, $buyable];
    }

    private static function assertComponentPricing(ProductComponent $component): void
    {
        if ($component->unit_price !== null && $component->unit_price < 0) {
            throw new InvalidCompositePackageException('A component unit price must be a non-negative integer in minor units.');
        }

        if ($component->metadata !== null && ! is_array($component->metadata)) {
            throw new InvalidCompositePackageException('Composite component metadata must be an array.');
        }
    }

    private static function syncParentShipping(ProductComponent $component): void
    {
        $parent = Product::query()->whereKey($component->product_id)->first();

        if ($parent instanceof Product) {
            $parent->save();
        }
    }

    protected static function newFactory(): ProductComponentFactory
    {
        return ProductComponentFactory::new();
    }
}
