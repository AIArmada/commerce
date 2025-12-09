<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string $name
 * @property string|null $english_name
 * @property string|null $description
 * @property int $quantity
 * @property int $weight_grams
 * @property string $unit_price
 * @property string $currency
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JntOrder $order
 */
final class JntOrderItem extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'name',
        'english_name',
        'description',
        'quantity',
        'weight_grams',
        'unit_price',
        'currency',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['order_items'] ?? $prefix . 'order_items';
    }

    /**
     * Get the order that owns this item.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Get the weight in kilograms.
     */
    public function getWeightInKilograms(): float
    {
        return $this->weight_grams / 1000;
    }

    /**
     * Get the total price for this item.
     */
    public function getTotalPrice(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'weight_grams' => 'integer',
            'metadata' => 'array',
        ];
    }
}
