<?php

declare(strict_types=1);

namespace AIArmada\Cart\Snapshots;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Database\Factories\CartSnapshotFactory;
use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Events\CartCheckoutStarted;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
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
class CartSnapshot extends Model
{
    /**
     * This model cannot use cascade deletes because there is a dedicated cart sync manager
     * that handles the synchronization and cleanup of cart items and conditions.
     * Cascade handling is managed at the application level through the sync manager.
     */

    /** @use HasFactory<CartSnapshotFactory> */
    use HasFactory;

    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'cart.owner';

    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(
            enabled: (bool) config('cart.owner.enabled', false),
            includeGlobal: (bool) config('cart.owner.include_global', false),
            autoAssignOnCreate: (bool) config('cart.owner.auto_assign_on_create', true),
        );
    }

    /** @var list<string> */
    protected $hidden = [
        'owner_scope',
    ];

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
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'last_activity_at' => 'immutable_datetime',
        'checkout_started_at' => 'immutable_datetime',
        'checkout_abandoned_at' => 'immutable_datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (! is_string($this->attributes['currency'] ?? null) || $this->attributes['currency'] === '') {
            $this->setAttribute('currency', CartMoney::currency());
        }
    }

    protected $attributes = [
        'items' => null,
        'conditions' => null,
        'metadata' => null,
        'items_count' => 0,
        'quantity' => 0,
        'subtotal' => 0,
        'total' => 0,
        'savings' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            $currency = $cart->getAttributes()['currency'] ?? null;

            if (! is_string($currency) || $currency === '') {
                $cart->setAttribute('currency', CartMoney::currency());
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return CartSnapshotFactory::new();
    }

    public function getTable(): string
    {
        return (string) config('cart.database.tables.snapshots', 'cart_snapshots');
    }

    public static function ownerScopingEnabled(): bool
    {
        return (bool) config('cart.owner.enabled', false);
    }

    public static function includeGlobalRecords(): bool
    {
        return (bool) config('cart.owner.include_global', false);
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
        return CartMoney::majorFromMinor($this->subtotal, $this->currency);
    }

    public function getTotalInDollarsAttribute(): float
    {
        return CartMoney::majorFromMinor($this->total, $this->currency);
    }

    public function getSavingsInDollarsAttribute(): float
    {
        return CartMoney::majorFromMinor($this->savings, $this->currency);
    }

    /** @return HasMany<CartSnapshotItem, CartSnapshot> */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartSnapshotItem::class, 'cart_id');
    }

    /** @return HasMany<CartSnapshotItem, CartSnapshot> */
    public function items(): HasMany
    {
        return $this->cartItems();
    }

    /** @return HasMany<CartSnapshotCondition, CartSnapshot> */
    public function cartConditions(): HasMany
    {
        return $this->hasMany(CartSnapshotCondition::class, 'cart_id');
    }

    /** @return HasMany<CartSnapshotCondition, CartSnapshot> */
    public function cartLevelConditions(): HasMany
    {
        return $this->cartConditions()->cartLevel();
    }

    /** @return HasMany<CartSnapshotCondition, CartSnapshot> */
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
        return CartMoney::formatMinor($amount, $this->currency);
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
        $query->where('updated_at', '>=', CarbonImmutable::now()->subDays($days));
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

    protected function currency(): Attribute
    {
        return Attribute::get(fn (?string $value): string => CartMoney::currency($value));
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
                'checkout_started_at' => CarbonImmutable::now(),
                'last_activity_at' => CarbonImmutable::now(),
            ]);

            event(CartCheckoutStarted::fromCart($this));
        } else {
            $this->update([
                'last_activity_at' => CarbonImmutable::now(),
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
                'checkout_abandoned_at' => CarbonImmutable::now(),
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
        $this->update(['last_activity_at' => CarbonImmutable::now()]);

        return $this;
    }
}
