<?php

declare(strict_types=1);

namespace AIArmada\Orders\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string|null $user_id
 * @property string $content
 * @property bool $is_customer_visible
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
class OrderNote extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'user_id',
        'content',
        'is_customer_visible',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_customer_visible' => false,
    ];

    public function getTable(): string
    {
        return config('orders.database.tables.order_notes', 'order_notes');
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Order, OrderNote>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    /**
     * Scope to only internal notes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<OrderNote>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OrderNote>
     */
    public function scopeInternal($query)
    {
        return $query->where('is_customer_visible', false);
    }

    /**
     * Scope to only customer-visible notes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<OrderNote>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OrderNote>
     */
    public function scopeCustomerVisible($query)
    {
        return $query->where('is_customer_visible', true);
    }

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
        ];
    }
}
