<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @property string $identifier
 * @property string $instance
 * @property array<mixed>|null $items
 * @property array<mixed>|null $conditions
 * @property array<mixed>|null $metadata
 * @property int $items_count
 * @property int $quantity
 * @property int $subtotal
 * @property int $total
 * @property int $savings
 * @property string $currency
 */
class Cart extends Model
{
    /** @use HasFactory<\AIArmada\FilamentCart\Database\Factories\CartFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'cart_snapshots';

    protected $fillable = [
        'identifier',
        'instance',
        'items',
        'conditions',
        'metadata',
        'items_count',
        'quantity',
        'subtotal',
        'total',
        'savings',
        'currency',
    ];

    protected $casts = [
        'items' => 'array',
        'conditions' => 'array',
        'metadata' => 'array',
        'items_count' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
        'total' => 'integer',
        'savings' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'items' => null,
        'conditions' => null,
        'metadata' => null,
        'items_count' => 0,
        'quantity' => 0,
        'subtotal' => 0,
        'total' => 0,
        'savings' => 0,
        'currency' => 'USD',
    ];

    public function getCartInstance(): ?BaseCart
    {
        try {
            return app(CartInstanceManager::class)->resolve($this->instance, $this->identifier);
        } catch (Throwable $exception) {
            Log::warning('Failed to resolve cart instance', [
                'identifier' => $this->identifier,
                'instance' => $this->instance,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function getSubtotalInDollarsAttribute(): float
    {
        return $this->subtotal / 100;
    }

    public function getTotalInDollarsAttribute(): float
    {
        return $this->total / 100;
    }

    public function getSavingsInDollarsAttribute(): float
    {
        return $this->savings / 100;
    }

    /** @return HasMany<CartItem, Cart> */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return HasMany<CartItem, Cart> */
    public function items(): HasMany
    {
        return $this->cartItems();
    }

    /** @return HasMany<CartCondition, Cart> */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cartConditions(): HasMany
    {
        return $this->hasMany(CartCondition::class);
    }

    /** @return HasMany<CartCondition, Cart> */
    /** @phpstan-ignore method.notFound, missingType.generics */
    public function cartLevelConditions(): HasMany
    {
        /** @phpstan-ignore-next-line */
        return $this->cartConditions()->cartLevel();
    }

    /** @return HasMany<CartCondition, Cart> */
    /** @phpstan-ignore method.notFound, missingType.generics */
    public function itemLevelConditions(): HasMany
    {
        /** @phpstan-ignore-next-line */
        return $this->cartConditions()->itemLevel();
    }

    /** @phpstan-ignore-next-line */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', \Illuminate\Foundation\Auth\User::class);

        return $this->belongsTo($userModel, 'identifier', 'id');
    }

    public function isEmpty(): bool
    {
        return $this->items_count === 0 || $this->quantity === 0;
    }

    public function formatMoney(int $amount): string
    {
        $currency = mb_strtoupper($this->currency ?: config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($amount);
    }

    protected static function newFactory(): \AIArmada\FilamentCart\Database\Factories\CartFactory
    {
        return \AIArmada\FilamentCart\Database\Factories\CartFactory::new();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function instance(Builder $query, string $instance): void
    {
        $query->where('instance', $instance);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byIdentifier(Builder $query, string $identifier): void
    {
        $query->where('identifier', $identifier);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function notEmpty(Builder $query): void
    {
        $query->where('items_count', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function recent(Builder $query, int $days = 7): void
    {
        $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function withSavings(Builder $query): void
    {
        $query->where('savings', '>', 0);
    }

    /** @return Attribute<string, never> */
    protected function formattedSubtotal(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatMoney($this->subtotal));
    }

    /** @return Attribute<string, never> */
    protected function formattedTotal(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatMoney($this->total));
    }

    /** @return Attribute<string, never> */
    protected function formattedSavings(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatMoney($this->savings));
    }
}
