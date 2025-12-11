<?php

declare(strict_types=1);

namespace AIArmada\Orders\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string|null $purchasable_id
 * @property string|null $purchasable_type
 * @property string $name
 * @property string|null $sku
 * @property int $quantity
 * @property int $unit_price
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $total
 * @property string $currency
 * @property array|null $options
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
class OrderItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'purchasable_id',
        'purchasable_type',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'total',
        'currency',
        'options',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'currency' => 'MYR',
    ];

    public function getTable(): string
    {
        return config('orders.database.tables.order_items', 'order_items');
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Order, OrderItem>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Polymorphic relationship to the purchasable (Product, Variant, etc.)
     *
     * @return MorphTo<Model, $this>
     */
    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Calculate the line total based on quantity, price, discount, and tax.
     */
    public function calculateTotal(): int
    {
        $subtotal = $this->quantity * $this->unit_price;
        $afterDiscount = $subtotal - $this->discount_amount;

        return $afterDiscount + $this->tax_amount;
    }

    /**
     * Get the formatted unit price.
     */
    public function getFormattedUnitPrice(): string
    {
        return $this->formatMoney($this->unit_price);
    }

    /**
     * Get the formatted total.
     */
    public function getFormattedTotal(): string
    {
        return $this->formatMoney($this->total);
    }

    // ─────────────────────────────────────────────────────────────
    // BOOT
    // ─────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $item->total = $item->calculateTotal();
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'options' => 'array',
            'metadata' => 'array',
        ];
    }

    protected function formatMoney(int $amountInCents): string
    {
        $decimalPlaces = config('orders.currency.decimal_places', 2);
        $symbol = match ($this->currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $this->currency . ' ',
        };

        return $symbol . number_format($amountInCents / 100, $decimalPlaces);
    }
}
