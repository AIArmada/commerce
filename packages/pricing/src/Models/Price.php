<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a price for a specific product/variant in a price list.
 *
 * @property string $id
 * @property string $price_list_id
 * @property string $priceable_id
 * @property string $priceable_type
 * @property int $amount
 * @property int|null $compare_amount
 * @property string $currency
 * @property int $min_quantity
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
class Price extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
        'compare_amount' => 'integer',
        'min_quantity' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_quantity' => 1,
        'currency' => 'MYR',
    ];

    public function getTable(): string
    {
        return config('pricing.tables.prices', 'prices');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The priceable item (Product, Variant, etc.).
     */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The price list this price belongs to.
     *
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeForQuantity($query, int $quantity)
    {
        return $query->where('min_quantity', '<=', $quantity);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        $now = now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        return true;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_amount && $this->compare_amount > $this->amount;
    }

    public function getDiscountPercentage(): ?float
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        return round((($this->compare_amount - $this->amount) / $this->compare_amount) * 100, 1);
    }

    public function getFormattedAmount(): string
    {
        $symbol = match ($this->currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'SGD' => 'S$',
            default => $this->currency . ' ',
        };

        return $symbol . number_format($this->amount / 100, 2);
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'compare_amount', 'min_quantity', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->useLogName('pricing');
    }
}
