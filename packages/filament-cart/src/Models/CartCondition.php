<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Normalized CartCondition model for performance and searchability
 *
 * This model is readonly and only updated via cart events
 * to maintain data consistency with the cart package.
 *
 * @property int $cart_id
 * @property int|null $cart_item_id
 * @property string $name
 * @property string $type
 * @property string $target
 * @property array<mixed>|null $target_definition
 * @property string $value
 * @property int $order
 * @property array<mixed>|null $attributes
 * @property string|null $item_id
 * @property string|null $operator
 * @property bool $is_charge
 * @property bool $is_dynamic
 * @property bool $is_discount
 * @property bool $is_percentage
 * @property bool $is_global
 * @property string|null $parsed_value
 * @property array<mixed>|null $rules
 */
final class CartCondition extends Model
{
    /** @use HasFactory<\AIArmada\FilamentCart\Database\Factories\CartConditionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The table associated with the model.
     */
    protected $table = 'cart_snapshot_conditions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'cart_id',
        'cart_item_id',
        'name',
        'type',
        'target',
        'target_definition',
        'value',
        'order',
        'attributes',
        'item_id', // The cart item ID this condition applies to (if item-level)
        'operator',
        'is_charge',
        'is_dynamic',
        'is_discount',
        'is_percentage',
        'is_global',
        'parsed_value',
        'rules',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'value' => 'string', // Keep as string to handle percentages and fixed amounts
        'parsed_value' => 'string',
        'order' => 'integer',
        'attributes' => 'array',
        'rules' => 'array',
        'target_definition' => 'array',
        'is_charge' => 'boolean',
        'is_dynamic' => 'boolean',
        'is_discount' => 'boolean',
        'is_percentage' => 'boolean',
        'is_global' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the cart that owns this condition.
     *
     * @return BelongsTo<Cart, CartCondition>
     */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the cart item this condition applies to (if item-level).
     *
     * @return BelongsTo<CartItem, CartCondition>
     */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    /**
     * Check if this is a cart-level condition.
     */
    public function isCartLevel(): bool
    {
        return $this->cart_item_id === null && $this->item_id === null;
    }

    /**
     * Check if this is an item-level condition.
     */
    public function isItemLevel(): bool
    {
        return $this->cart_item_id !== null || $this->item_id !== null;
    }

    /**
     * Check if condition is a discount.
     */
    public function isDiscount(): bool
    {
        return $this->type === 'discount';
    }

    /**
     * Check if condition is a tax.
     */
    public function isTax(): bool
    {
        return $this->type === 'tax';
    }

    /**
     * Check if condition is a fee.
     */
    public function isFee(): bool
    {
        return $this->type === 'fee';
    }

    /**
     * Check if condition is shipping.
     */
    public function isShipping(): bool
    {
        return $this->type === 'shipping';
    }

    /**
     * Check if value is a percentage.
     */
    public function isPercentage(): bool
    {
        return str_contains($this->value, '%');
    }

    /**
     * Get the condition level label.
     */
    public function getLevelAttribute(): string
    {
        return $this->isItemLevel() ? 'Item' : 'Cart';
    }

    /**
     * Get formatted value for display.
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->isPercentage()) {
            return $this->value;
        }

        $rawValue = $this->value;
        $normalized = mb_ltrim($rawValue, '+');
        $money = Money::{$this->resolveCurrency()}($normalized);

        return str_starts_with($rawValue, '+')
            ? '+'.$money
            : (string) $money;
    }

    /**
     * Get attributes count.
     */
    public function getAttributesCountAttribute(): int
    {
        return ! empty($this->attributes) && is_array($this->attributes) ? count($this->attributes) : 0;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \AIArmada\FilamentCart\Database\Factories\CartConditionFactory
    {
        return \AIArmada\FilamentCart\Database\Factories\CartConditionFactory::new();
    }

    /**
     * Scope to filter by cart instance.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function instance(Builder $query, string $instance): void
    {
        $query->whereHas('cart', function ($q) use ($instance): void {
            $q->where('instance', $instance);
        });
    }

    /**
     * Scope to filter by cart identifier.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byIdentifier(Builder $query, string $identifier): void
    {
        $query->whereHas('cart', function ($q) use ($identifier): void {
            $q->where('identifier', $identifier);
        });
    }

    /**
     * Scope to filter by condition type.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope to filter by condition target.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byTarget(Builder $query, string $target): void
    {
        $query->where('target', $target);
    }

    /**
     * Scope to get cart-level conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function cartLevel(Builder $query): void
    {
        $query->whereNull('cart_item_id')->whereNull('item_id');
    }

    /**
     * Scope to get item-level conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function itemLevel(Builder $query): void
    {
        $query->where(function ($q): void {
            $q->whereNotNull('cart_item_id')->orWhereNotNull('item_id');
        });
    }

    /**
     * Scope to get discount conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function discounts(Builder $query): void
    {
        $query->where('type', 'discount');
    }

    /**
     * Scope to get tax conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function taxes(Builder $query): void
    {
        $query->where('type', 'tax');
    }

    /**
     * Scope to get fee conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function fees(Builder $query): void
    {
        $query->where('type', 'fee');
    }

    /**
     * Scope to get shipping conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function shipping(Builder $query): void
    {
        $query->where('type', 'shipping');
    }

    private function resolveCurrency(): string
    {
        return mb_strtoupper($this->cart->currency ?? config('cart.money.default_currency', 'USD'));
    }
}
