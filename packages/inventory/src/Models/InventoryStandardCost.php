<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property int $standard_cost_minor
 * @property string $currency
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property string|null $approved_by
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $inventoryable
 */
class InventoryStandardCost extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'standard_cost_minor',
        'currency',
        'effective_from',
        'effective_to',
        'approved_by',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('inventory.table_names.standard_costs', 'inventory_standard_costs');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForModel(\Illuminate\Database\Eloquent\Builder $query, Model $model): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCurrent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeEffectiveAt(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Support\Carbon $date): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $date);
            });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFuture(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('effective_from', '>', now());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeExpired(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('effective_to')
            ->where('effective_to', '<=', now());
    }

    public function isCurrent(): bool
    {
        $now = now();

        return $this->effective_from <= $now
            && ($this->effective_to === null || $this->effective_to > $now);
    }

    public function isFuture(): bool
    {
        return $this->effective_from > now();
    }

    public function isExpired(): bool
    {
        return $this->effective_to !== null && $this->effective_to <= now();
    }

    public function expire(): bool
    {
        return $this->update(['effective_to' => now()]);
    }

    protected function casts(): array
    {
        return [
            'standard_cost_minor' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
