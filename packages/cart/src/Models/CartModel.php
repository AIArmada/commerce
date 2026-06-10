<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Models\Concerns\HasCartOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cart Eloquent Model for database persistence.
 *
 * This model provides an Eloquent interface to cart data stored in the database.
 * It works alongside the Cart value object and DatabaseStorage for hybrid access patterns.
 *
 * @property string $id
 * @property string $identifier
 * @property string $instance
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $items
 * @property array<string, mixed>|null $conditions
 * @property array<string, mixed>|null $metadata
 * @property int $version
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $checked_out_at
 * @property CarbonImmutable|null $abandoned_at
 * @property string|null $merged_into_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CartCollection<int, CartItem> $cartItems
 * @property-read CartConditionCollection<int, CartCondition> $cartConditions
 */
class CartModel extends Model implements Auditable
{
    use HasCartOwner;
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'identifier',
        'instance',
        'owner_type',
        'owner_id',
        'items',
        'conditions',
        'metadata',
        'version',
        'expires_at',
        'expired_at',
        'checked_out_at',
        'abandoned_at',
        'merged_into_id',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('cart.database.table', 'carts');
    }

    /**
     * Get items as a CartCollection of CartItem objects.
     *
     * @return CartCollection<int, CartItem>
     */
    public function getCartItemsAttribute(): CartCollection
    {
        $items = $this->items ?? [];
        $cartItems = [];

        foreach ($items as $itemData) {
            if (is_array($itemData)) {
                $cartItems[] = CartItem::fromArray($itemData);
            }
        }

        return new CartCollection($cartItems);
    }

    /**
     * Get conditions as a CartConditionCollection.
     *
     * @return CartConditionCollection<int, CartCondition>
     */
    public function getCartConditionsAttribute(): CartConditionCollection
    {
        $conditions = $this->conditions ?? [];
        $cartConditions = [];

        foreach ($conditions as $conditionData) {
            if (is_array($conditionData)) {
                $cartConditions[] = CartCondition::fromArray($conditionData);
            }
        }

        return new CartConditionCollection($cartConditions);
    }

    /**
     * Check if cart has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expired_at !== null) {
            return true;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if cart has been converted to an order.
     */
    public function isConverted(): bool
    {
        return $this->checked_out_at !== null;
    }

    /**
     * Check if cart has been marked as abandoned.
     */
    public function isAbandoned(): bool
    {
        return $this->abandoned_at !== null;
    }

    /**
     * Check if cart has been merged into another cart.
     */
    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    /**
     * Mark cart as converted (checked out).
     */
    public function markAsConverted(): void
    {
        $this->checked_out_at = CarbonImmutable::now();
        $this->save();
    }

    /**
     * Check if cart is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get the number of items in the cart.
     */
    public function getItemCount(): int
    {
        return count($this->items ?? []);
    }

    /**
     * Get total quantity of all items.
     */
    public function getTotalQuantity(): int
    {
        $items = $this->items ?? [];
        $total = 0;

        foreach ($items as $item) {
            if (is_array($item) && isset($item['quantity'])) {
                $total += (int) $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Get metadata value by key.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Application-level cascade delete.
     */
    protected static function booted(): void
    {
        // No cascades needed - carts are standalone
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'conditions' => 'array',
            'metadata' => 'array',
            'version' => 'integer',
            'expires_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'abandoned_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Scope to filter by identifier.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forIdentifier(Builder $query, string $identifier): void
    {
        $query->where('identifier', $identifier);
    }

    /**
     * Scope to filter by instance.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forInstance(Builder $query, string $instance): void
    {
        $query->where('instance', $instance);
    }

    /**
     * Scope to find by identifier and instance.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forCart(Builder $query, string $identifier, string $instance): void
    {
        $query->where('identifier', $identifier)
            ->where('instance', $instance);
    }

    /**
     * Scope to filter expired carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNotNull('expired_at')
                ->orWhere(function (Builder $q2): void {
                    $q2->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());
                });
        });
    }

    /**
     * Scope to filter non-expired carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function notExpired(Builder $query): void
    {
        $query->whereNull('expired_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to filter converted carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function converted(Builder $query): void
    {
        $query->whereNotNull('checked_out_at');
    }

    /**
     * Scope to filter abandoned carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function abandoned(Builder $query): void
    {
        $query->whereNotNull('abandoned_at');
    }

    /**
     * Scope to filter non-empty carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withItems(Builder $query): void
    {
        // DB-agnostic approach: check for non-empty JSON array
        $query->whereNotNull('items');

        $driver = ConnectionDriver::name($query->getConnection());
        if ($driver === 'pgsql') {
            $query->whereRaw("items::text != '[]'")
                ->whereRaw("items::text != '{}'");
        } else {
            $query->where('items', '!=', '[]')
                ->where('items', '!=', '{}');
        }
    }

    /**
     * Scope to filter carts inactive for specified minutes.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inactiveFor(Builder $query, int $minutes): void
    {
        $threshold = now()->subMinutes($minutes);
        $query->where('updated_at', '<', $threshold);
    }

    /**
     * Scope to order by most recent activity.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function recentActivity(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }
}
