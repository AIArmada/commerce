<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a tax class for categorizing products.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $position
 */
class TaxClass extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'position' => 0,
    ];

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Get the default tax class.
     */
    public static function getDefault(): ?self
    {
        return static::default()->first();
    }

    /**
     * Get a tax class by slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public function getTable(): string
    {
        return config('tax.tables.tax_classes', 'tax_classes');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position', 'asc');
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_default', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
