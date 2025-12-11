<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OptionValue extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'swatch_color' => 'string',
        'metadata' => 'array',
    ];

    /**
     * @var array<int, string>
     */
    protected $attributes = [
        'position' => 0,
    ];

    public function getTable(): string
    {
        return config('products.tables.option_values', 'product_option_values');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent option.
     *
     * @return BelongsTo<Option, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }

    /**
     * Get the variants using this option value.
     *
     * @return BelongsToMany<Variant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            Variant::class,
            config('products.tables.variant_options', 'product_variant_options'),
            'option_value_id',
            'variant_id'
        );
    }

    // =========================================================================
    // SWATCH HELPERS
    // =========================================================================

    /**
     * Check if this option value has a color swatch.
     */
    public function hasColorSwatch(): bool
    {
        return ! empty($this->swatch_color);
    }

    /**
     * Check if this option value has an image swatch.
     */
    public function hasImageSwatch(): bool
    {
        return ! empty($this->swatch_image);
    }

    /**
     * Get the swatch style for CSS.
     */
    public function getSwatchStyle(): ?string
    {
        if ($this->hasColorSwatch()) {
            return "background-color: {$this->swatch_color}";
        }

        if ($this->hasImageSwatch()) {
            return "background-image: url('{$this->swatch_image}')";
        }

        return null;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }
}
