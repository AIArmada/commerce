<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Products\Traits\HasAttributes;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $product_id
 * @property string $sku
 * @property string|null $barcode
 * @property int|null $price
 * @property int|null $compare_price
 * @property int|null $cost
 * @property float|null $weight
 * @property float|null $length
 * @property float|null $width
 * @property float|null $height
 * @property bool $is_default
 * @property bool $is_enabled
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Product $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OptionValue> $optionValues
 * @property-read Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $display_images
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeValue> $attributeValues
 */
class Variant extends Model implements HasMedia, Priceable
{
    use HasAttributes;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'integer',
        'compare_price' => 'integer',
        'cost' => 'integer',
        'weight' => 'decimal:2',
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_enabled' => true,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['variants'] ?? $prefix . 'variants';
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
     * Get the option values for this variant.
     *
     * @return BelongsToMany<OptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            OptionValue::class,
            config('products.database.tables.variant_options', 'product_variant_options'),
            'variant_id',
            'option_value_id'
        );
    }

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        /** @var array{mimes?:array<int,string>} $variantImages */
        $variantImages = config('products.media.collections.variant_images', []);

        $this->addMediaCollection('variant_images')
            ->acceptsMimeTypes($variantImages['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);
    }

    // =========================================================================
    // IMAGE HELPERS
    // =========================================================================

    /**
     * Get display images - variant specific or fall back to product.
     */
    public function getDisplayImagesAttribute(): Collection
    {
        $variantImages = $this->getMedia('variant_images');

        if ($variantImages->isNotEmpty()) {
            return $variantImages;
        }

        return $this->product->getMedia('gallery');
    }

    public function getFeaturedImageUrl(string $conversion = 'card'): ?string
    {
        $variantImage = $this->getFirstMedia('variant_images');
        if ($variantImage) {
            return $variantImage->getUrl($conversion);
        }

        return $this->product->getFeaturedImageUrl($conversion);
    }

    // =========================================================================
    // PRICE HELPERS
    // =========================================================================

    /**
     * Get the effective price (variant price or parent product price).
     */
    public function getEffectivePrice(): int
    {
        return $this->price ?? $this->product->price;
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPrice(): string
    {
        $currency = mb_strtoupper($this->product?->currency ?: config('products.defaults.currency', 'MYR'));
        $asMajorUnits = ! (bool) config('products.defaults.store_money_in_cents', true);

        return Money::$currency($this->getEffectivePrice(), $asMajorUnits)->format();
    }

    /**
     * Get effective compare price.
     */
    public function getEffectiveComparePrice(): ?int
    {
        return $this->compare_price ?? $this->product->compare_price;
    }

    public function getBuyableIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getBasePrice(): int
    {
        return $this->getEffectivePrice();
    }

    public function getComparePrice(): ?int
    {
        return $this->getEffectiveComparePrice();
    }

    public function isOnSale(): bool
    {
        $comparePrice = $this->getComparePrice();

        if ($comparePrice === null) {
            return false;
        }

        return $comparePrice > $this->getBasePrice();
    }

    public function getDiscountPercentage(): ?float
    {
        $comparePrice = $this->getComparePrice();

        if (! $this->isOnSale() || $comparePrice === null || $comparePrice === 0) {
            return null;
        }

        return (1 - ($this->getBasePrice() / $comparePrice)) * 100;
    }

    /**
     * Get formatted compare price.
     */
    public function getFormattedComparePrice(): ?string
    {
        $comparePrice = $this->getEffectiveComparePrice();

        if (! $comparePrice) {
            return null;
        }

        $currency = mb_strtoupper($this->product?->currency ?: config('products.defaults.currency', 'MYR'));
        $asMajorUnits = ! (bool) config('products.defaults.store_money_in_cents', true);

        return Money::$currency($comparePrice, $asMajorUnits)->format();
    }

    // =========================================================================
    // OPTION HELPERS
    // =========================================================================

    /**
     * Get the option values as a readable string.
     * e.g., "Red / Large"
     */
    public function getOptionSummary(): string
    {
        return $this->optionValues()
            ->with('option')
            ->get()
            ->sortBy('option.position')
            ->pluck('name')
            ->implode(' / ');
    }

    /**
     * Get the full variant name including product name.
     * e.g., "T-Shirt - Red / Large"
     */
    public function getFullName(): string
    {
        $summary = $this->getOptionSummary();

        if (empty($summary)) {
            return $this->product->name;
        }

        return "{$this->product->name} - {$summary}";
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function isPurchasable(): bool
    {
        return $this->is_enabled && $this->product->isPurchasable();
    }

    // =========================================================================
    // SKU GENERATION
    // =========================================================================

    /**
     * Generate a SKU based on the configured pattern.
     */
    public function generateSku(): string
    {
        $pattern = config('products.features.variants.sku_pattern', '{parent_sku}-{option_codes}');

        $optionCodes = $this->optionValues()
            ->with('option')
            ->get()
            ->sortBy('option.position')
            ->map(fn ($opt) => mb_strtoupper(mb_substr($opt->name, 0, 2)))
            ->implode('-');

        return str_replace(
            ['{parent_sku}', '{option_codes}'],
            [$this->product->sku ?? 'PROD', $optionCodes],
            $pattern
        );
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (Variant $variant): void {
            $variant->optionValues()->detach();
        });
    }
}
