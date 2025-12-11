<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Option extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'is_visible' => 'boolean',
    ];

    /**
     * @var array<int, string>
     */
    protected $attributes = [
        'position' => 0,
        'is_visible' => true,
    ];

    public function getTable(): string
    {
        return config('products.tables.options', 'product_options');
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

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (Option $option): void {
            $option->values()->delete();
        });
    }
}
