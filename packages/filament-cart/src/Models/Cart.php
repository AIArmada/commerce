<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use AIArmada\FilamentCart\Database\Factories\CartFactory;
use AIArmada\FilamentCart\Events\CartAbandoned;
use AIArmada\FilamentCart\Events\CartCheckoutStarted;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
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
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $owner_scope
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $checkout_started_at
 * @property Carbon|null $checkout_abandoned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Cart extends Model
{
    /**
     * This model cannot use cascade deletes because there is a dedicated cart sync manager
     * that handles the synchronization and cleanup of cart items and conditions.
     * Cascade handling is managed at the application level through the sync manager.
     */

    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'filament-cart.owner';

    /** @var list<string> */
    protected $hidden = [
        'owner_scope',
    ];

    protected $fillable = [
        'owner_type',
        'owner_id',
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
        'last_activity_at',
        'checkout_started_at',
        'checkout_abandoned_at',
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
        'last_activity_at' => 'datetime',
        'checkout_started_at' => 'datetime',
        'checkout_abandoned_at' => 'datetime',
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

    public function getTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['snapshots'] ?? $prefix . 'snapshots';
    }

    public static function ownerScopingEnabled(): bool
    {
        return (bool) config('filament-cart.owner.enabled', false);
    }

    public static function resolveCurrentOwner(): ?EloquentModel
    {
        if (! self::ownerScopingEnabled()) {
            return null;
        }

        /** @var EloquentModel|null $owner */
        $owner = OwnerContext::resolve();

        return $owner;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, EloquentModel | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! self::ownerScopingEnabled()) {
            return $query;
        }

        if ($owner === OwnerContext::CURRENT) {
            $owner = self::resolveCurrentOwner();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                self::class . ' requires an owner context or explicit global context.',
            );
        }

        if (is_string($owner)) {
            throw new InvalidArgumentException('Owner must be an Eloquent model, null, or omitted.');
        }

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            self::class . ' requires an owner context or explicit global context.',
        );

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    public function getCartInstance(): ?BaseCart
    {
        try {
            return app(CartInstanceManager::class)->resolveForSnapshot($this);
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
    public function cartConditions(): HasMany
    {
        return $this->hasMany(CartCondition::class);
    }

    /** @return HasMany<CartCondition, Cart> */
    public function cartLevelConditions(): HasMany
    {
        return $this->cartConditions()->cartLevel();
    }

    /** @return HasMany<CartCondition, Cart> */
    public function itemLevelConditions(): HasMany
    {
        return $this->cartConditions()->itemLevel();
    }

    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', User::class);

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

    /**
     * Check if cart is abandoned.
     */
    public function isAbandoned(): bool
    {
        return $this->checkout_abandoned_at !== null;
    }

    /**
     * Check if cart is in checkout process.
     */
    public function isInCheckout(): bool
    {
        return $this->checkout_started_at !== null && $this->checkout_abandoned_at === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function instance(Builder $query, string $instance): void
    {
        $query->where('instance', $instance);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function byIdentifier(Builder $query, string $identifier): void
    {
        $query->where('identifier', $identifier);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function notEmpty(Builder $query): void
    {
        $query->where('items_count', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function recent(Builder $query, int $days = 7): void
    {
        $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withSavings(Builder $query): void
    {
        $query->where('savings', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function abandoned(Builder $query): void
    {
        $query->whereNotNull('checkout_abandoned_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inCheckout(Builder $query): void
    {
        $query->whereNotNull('checkout_started_at')
            ->whereNull('checkout_abandoned_at');
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

    /**
     * Mark that checkout has started for this cart.
     */
    public function markCheckoutStarted(): static
    {
        if ($this->checkout_started_at === null) {
            $this->update([
                'checkout_started_at' => now(),
                'last_activity_at' => now(),
            ]);

            event(CartCheckoutStarted::fromCart($this));
        } else {
            $this->update([
                'last_activity_at' => now(),
            ]);
        }

        return $this;
    }

    /**
     * Mark this cart as abandoned.
     */
    public function markAsAbandoned(): static
    {
        if ($this->checkout_abandoned_at === null) {
            $this->update([
                'checkout_abandoned_at' => now(),
            ]);

            event(CartAbandoned::fromCart($this));
        }

        return $this;
    }

    /**
     * Update last activity timestamp.
     */
    public function touchActivity(): static
    {
        $this->update(['last_activity_at' => now()]);

        return $this;
    }
}
