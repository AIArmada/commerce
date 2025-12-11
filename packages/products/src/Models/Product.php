<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Products\Contracts\Buyable;
use AIArmada\Products\Contracts\Inventoryable;
use AIArmada\Products\Contracts\Priceable;
use AIArmada\Products\Database\Factories\ProductFactory;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;

class Product extends Model implements Buyable, HasMedia, Inventoryable, Priceable
{
    use HasFactory;
    use HasOwner;
    use HasSlug;
    use HasTags;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => ProductType::class,
        'status' => ProductStatus::class,
        'visibility' => ProductVisibility::class,
        'price' => 'integer',
        'compare_price' => 'integer',
        'cost' => 'integer',
        'is_featured' => 'boolean',
        'is_taxable' => 'boolean',
        'requires_shipping' => 'boolean',
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * @var array<int, string>
     */
    protected $attributes = [
        'type' => 'simple',
        'status' => 'draft',
        'visibility' => 'catalog_search',
        'is_featured' => false,
        'is_taxable' => true,
        'requires_shipping' => true,
    ];

    public function getTable(): string
    {
        return config('products.tables.products', 'products');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the product's variants.
     *
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class, 'product_id');
    }

    /**
     * Get the product's options.
     *
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class, 'product_id');
    }

    /**
     * Get the categories the product belongs to.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            config('products.tables.category_product', 'category_product'),
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * Get the collections the product belongs to.
     *
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            Collection::class,
            config('products.tables.collection_product', 'collection_product'),
            'product_id',
            'collection_id'
        )->withTimestamps();
    }

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl('/images/product-placeholder.jpg')
            ->useFallbackPath(public_path('/images/product-placeholder.jpg'));

        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('videos')
            ->acceptsMimeTypes(['video/mp4', 'video/webm']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->optimize()
            ->nonQueued();

        $this->addMediaConversion('card')
            ->width(400)
            ->height(400)
            ->optimize()
            ->nonQueued();

        $this->addMediaConversion('detail')
            ->width(800)
            ->height(800)
            ->optimize()
            ->queued();

        $this->addMediaConversion('zoom')
            ->width(1600)
            ->height(1600)
            ->optimize()
            ->queued();

        $this->addMediaConversion('webp-card')
            ->width(400)
            ->height(400)
            ->format('webp')
            ->optimize()
            ->queued();
    }

    // =========================================================================
    // SPATIE SLUGGABLE
    // =========================================================================

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(100);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // =========================================================================
    // MONEY HELPERS
    // =========================================================================

    public function getFormattedPrice(): string
    {
        $currency = config('products.currency.default', 'MYR');

        return Money::$currency($this->price, true)->format();
    }

    public function getFormattedComparePrice(): ?string
    {
        if (! $this->compare_price) {
            return null;
        }

        $currency = config('products.currency.default', 'MYR');

        return Money::$currency($this->compare_price, true)->format();
    }

    public function getFormattedCost(): ?string
    {
        if (! $this->cost) {
            return null;
        }

        $currency = config('products.currency.default', 'MYR');

        return Money::$currency($this->cost, true)->format();
    }

    public function getPriceAsMoney(): Money
    {
        $currency = config('products.currency.default', 'MYR');

        return Money::$currency($this->price, true);
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    public function isDraft(): bool
    {
        return $this->status === ProductStatus::Draft;
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
    }

    public function isPurchasable(): bool
    {
        return $this->status->isPurchasable();
    }

    public function activate(): self
    {
        $this->status = ProductStatus::Active;
        $this->published_at ??= now();
        $this->save();

        return $this;
    }

    public function archive(): self
    {
        $this->status = ProductStatus::Archived;
        $this->save();

        return $this;
    }

    // =========================================================================
    // TYPE HELPERS
    // =========================================================================

    public function hasVariants(): bool
    {
        return $this->type->hasVariants() && $this->variants()->exists();
    }

    public function isPhysical(): bool
    {
        return $this->type->isPhysical();
    }

    public function isDigital(): bool
    {
        return $this->type === ProductType::Digital;
    }

    public function isSubscription(): bool
    {
        return $this->type === ProductType::Subscription;
    }

    // =========================================================================
    // PRICE HELPERS
    // =========================================================================

    public function hasDiscount(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    public function getDiscountPercentage(): ?float
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 1);
    }

    public function getProfitMargin(): ?float
    {
        if (! $this->cost || $this->cost === 0) {
            return null;
        }

        return round((($this->price - $this->cost) / $this->price) * 100, 1);
    }

    // =========================================================================
    // FEATURED IMAGE
    // =========================================================================

    public function getFeaturedImageUrl(string $conversion = 'card'): ?string
    {
        $hero = $this->getFirstMedia('hero');
        if ($hero) {
            return $hero->getUrl($conversion);
        }

        $gallery = $this->getFirstMedia('gallery');
        if ($gallery) {
            return $gallery->getUrl($conversion);
        }

        return $this->getFallbackMediaUrl('gallery');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('status', ProductStatus::Active)
            ->whereIn('visibility', [
                ProductVisibility::Catalog,
                ProductVisibility::CatalogSearch,
            ]);
    }

    public function scopeSearchable($query)
    {
        return $query->where('status', ProductStatus::Active)
            ->whereIn('visibility', [
                ProductVisibility::Search,
                ProductVisibility::CatalogSearch,
            ]);
    }

    public function scopeOfType($query, ProductType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeInCategory($query, Category $category)
    {
        return $query->whereHas('categories', function ($q) use ($category): void {
            $q->where('category_id', $category->id);
        });
    }

    public function scopePriceRange($query, int $min, int $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    // =========================================================================
    // BUYABLE INTERFACE
    // =========================================================================

    public function getBuyableIdentifier(): string
    {
        return $this->id;
    }

    public function getBuyableDescription(): string
    {
        return $this->name;
    }

    public function getBuyablePrice(): int
    {
        return $this->price ?? 0;
    }

    public function getBuyableWeight(): ?float
    {
        return $this->weight;
    }

    public function isBuyable(): bool
    {
        return $this->isPurchasable();
    }

    // =========================================================================
    // PRICEABLE INTERFACE
    // =========================================================================

    public function getBasePrice(): int
    {
        return $this->price ?? 0;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function getCalculatedPrice(array $context = []): int
    {
        // For now, return base price. Pricing package will extend this.
        return $this->getBasePrice();
    }

    public function getComparePrice(): ?int
    {
        return $this->compare_price;
    }

    public function isOnSale(): bool
    {
        return $this->hasDiscount();
    }

    // =========================================================================
    // INVENTORYABLE INTERFACE
    // =========================================================================

    public function getInventorySku(): string
    {
        return $this->sku ?? '';
    }

    public function getStockQuantity(): int
    {
        // Will integrate with inventory package
        return 0;
    }

    public function isInStock(): bool
    {
        // Will integrate with inventory package
        return true;
    }

    public function hasStock(int $quantity): bool
    {
        // Will integrate with inventory package
        return true;
    }

    public function tracksInventory(): bool
    {
        return ! $this->isDigital();
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (Product $product): void {
            $product->options()->delete();
            $product->variants()->delete();
            $product->categories()->detach();
            $product->collections()->detach();
        });
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
