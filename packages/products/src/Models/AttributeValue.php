<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $attribute_id
 * @property string $attributable_type
 * @property string $attributable_id
 * @property string|null $value
 * @property string|null $locale
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Attribute $attribute
 * @property-read Model $attributable
 * @property-read mixed $typed_value
 */
class AttributeValue extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['attribute_values'] ?? $prefix . 'attribute_values';
    }

    /**
     * Get the attribute definition.
     *
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * Get the owning model (Product or Variant).
     *
     * @return MorphTo<Model, $this>
     */
    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the value cast to the appropriate PHP type.
     */
    public function getTypedValueAttribute(): mixed
    {
        if (! $this->relationLoaded('attribute')) {
            $this->load('attribute');
        }

        return $this->attribute?->castValue($this->value);
    }

    /**
     * Set the value, serializing it appropriately.
     */
    public function setTypedValue(mixed $value): self
    {
        if (! $this->relationLoaded('attribute')) {
            $this->load('attribute');
        }

        $this->value = $this->attribute?->serializeValue($value);

        return $this;
    }

    /**
     * Scope to a specific locale.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeValue>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeValue>
     */
    public function scopeForLocale($query, ?string $locale = null)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Scope to a specific attribute by code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeValue>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeValue>
     */
    public function scopeForAttribute($query, string $code)
    {
        return $query->whereHas('attribute', fn ($q) => $q->where('code', $code));
    }
}
