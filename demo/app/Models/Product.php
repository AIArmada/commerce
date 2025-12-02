<?php

declare(strict_types=1);

namespace App\Models;

use AIArmada\Stock\Contracts\StockableInterface;
use AIArmada\Stock\Traits\HasStock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $sku
 * @property int $price Price in cents
 * @property int|null $compare_at_price Price in cents
 * @property string $currency
 * @property bool $is_active
 * @property bool $track_stock
 * @property int $stock_quantity
 * @property int $low_stock_threshold
 * @property string|null $category_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Product extends Model implements StockableInterface
{
    use HasFactory;
    use HasStock;
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sku',
        'price',
        'compare_at_price',
        'currency',
        'is_active',
        'track_stock',
        'stock_quantity',
        'low_stock_threshold',
        'category_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get price formatted in MYR.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'RM ' . number_format($this->price / 100, 2);
    }

    /**
     * Check if product is in stock.
     */
    public function isInStock(): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return $this->getCurrentStock() > 0;
    }

    /**
     * Check if product is out of stock.
     */
    public function isOutOfStock(): bool
    {
        if (! $this->track_stock) {
            return false;
        }

        return $this->getCurrentStock() <= 0;
    }
}
