<?php

declare(strict_types=1);

namespace AIArmada\Orders\Models;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $order_id
 * @property string|null $user_id
 * @property string|null $owner_id
 * @property string|null $owner_type
 * @property string $content
 * @property bool $is_customer_visible
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
class OrderNote extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'owner_id',
        'owner_type',
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = true): Builder
    {
        if (! (bool) config('orders.owner.enabled', true)) {
            return $query;
        }

        if ($owner === null && app()->bound(OwnerResolverInterface::class)) {
            $owner = app(OwnerResolverInterface::class)->resolve();
        }

        $includeGlobal = $includeGlobal && (bool) config('orders.owner.include_global', true);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
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
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('is_customer_visible', false);
    }

    /**
     * Scope to only customer-visible notes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<OrderNote>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OrderNote>
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCustomerVisible(Builder $query): Builder
    {
        return $query->where('is_customer_visible', true);
    }

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrderNote $note): void {
            if (! (bool) config('orders.owner.enabled', true)) {
                return;
            }

            if (blank($note->order_id)) {
                throw new InvalidArgumentException('order_id is required.');
            }

            $orderQuery = Order::query();
            if (app()->bound(OwnerResolverInterface::class)) {
                $orderQuery->forOwner();
            }

            $order = $orderQuery->findOrFail($note->order_id);

            if ($order->owner_type !== null && $order->owner_id !== null) {
                $note->owner_type = $order->owner_type;
                $note->owner_id = $order->owner_id;
            } else {
                $note->owner_type = null;
                $note->owner_id = null;
            }
        });
    }
}
