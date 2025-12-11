<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a price list (e.g., Retail, Wholesale, VIP).
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $currency
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
class PriceList extends Model
{
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 0,
        'is_default' => false,
        'is_active' => true,
    ];

    public function getTable(): string
    {
        return config('pricing.tables.price_lists', 'price_lists');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'priority', 'is_active', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->useLogName('pricing');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (PriceList $priceList): void {
            $priceList->prices()->delete();
        });
    }
}
