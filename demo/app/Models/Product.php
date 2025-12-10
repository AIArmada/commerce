<?php

declare(strict_types=1);

namespace App\Models;

use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Traits\HasInventory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $available_stock
 */
final class Product extends Model implements InventoryableInterface
{
    use HasFactory;
    use HasInventory;
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

    public function isLowInventory(?int $threshold = null): bool
    {
        if (! $this->track_stock) {
            return false;
        }

        $limit = $threshold ?? $this->low_stock_threshold;

        return $this->getCurrentStock() <= $limit;
    }

    public function isLowStock(?int $threshold = null): bool
    {
        return $this->isLowInventory($threshold);
    }

    public function getCurrentStock(): int
    {
        if (! $this->track_stock) {
            return PHP_INT_MAX;
        }

        return $this->getTotalAvailable();
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->track_stock ? $this->getTotalAvailable() : PHP_INT_MAX;
    }

    public function removeStock(int $quantity, string $reason = 'sale', ?string $reference = null): void
    {
        if (! $this->track_stock) {
            return;
        }

        $location = InventoryLocation::getOrCreateDefault();

        $this->ship(
            $location->id,
            $quantity,
            $reason,
            $reference,
        );
    }

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
}
