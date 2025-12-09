<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\Inventory\Enums\DemandPeriodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventoryable_type
 * @property string $inventoryable_id
 * @property string|null $location_id
 * @property Carbon $period_date
 * @property DemandPeriodType $period_type
 * @property int $quantity_demanded
 * @property int $quantity_fulfilled
 * @property int $quantity_lost
 * @property int $order_count
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $inventoryable
 * @property-read InventoryLocation|null $location
 */
class InventoryDemandHistory extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'period_date',
        'period_type',
        'quantity_demanded',
        'quantity_fulfilled',
        'quantity_lost',
        'order_count',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('inventory.table_names.demand_history', 'inventory_demand_history');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * @return Builder<static>
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());
    }

    /**
     * @return Builder<static>
     */
    public function scopeDaily(Builder $query): Builder
    {
        return $query->where('period_type', DemandPeriodType::Daily->value);
    }

    /**
     * @return Builder<static>
     */
    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('period_type', DemandPeriodType::Weekly->value);
    }

    /**
     * @return Builder<static>
     */
    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('period_type', DemandPeriodType::Monthly->value);
    }

    /**
     * @return Builder<static>
     */
    public function scopeBetweenDates(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('period_date', [$from, $to]);
    }

    /**
     * @return Builder<static>
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('period_date', '>=', now()->subDays($days));
    }

    public function fulfillmentRate(): float
    {
        if ($this->quantity_demanded === 0) {
            return 100.0;
        }

        return ($this->quantity_fulfilled / $this->quantity_demanded) * 100;
    }

    public function lostSalesRate(): float
    {
        if ($this->quantity_demanded === 0) {
            return 0.0;
        }

        return ($this->quantity_lost / $this->quantity_demanded) * 100;
    }

    public function averageOrderSize(): float
    {
        if ($this->order_count === 0) {
            return 0.0;
        }

        return $this->quantity_demanded / $this->order_count;
    }

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'period_type' => DemandPeriodType::class,
            'quantity_demanded' => 'integer',
            'quantity_fulfilled' => 'integer',
            'quantity_lost' => 'integer',
            'order_count' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
