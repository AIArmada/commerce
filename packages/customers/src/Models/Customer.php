<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\CustomerCreated;
use AIArmada\Customers\Events\CustomerUpdated;
use AIArmada\Customers\Events\WalletCreditAdded;
use AIArmada\Customers\Events\WalletCreditDeducted;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property CustomerStatus $status
 * @property int $wallet_balance
 * @property int $lifetime_value
 * @property int $total_orders
 * @property bool $accepts_marketing
 * @property bool $is_tax_exempt
 * @property string|null $tax_exempt_reason
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_order_at
 * @property Carbon|null $last_login_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read Model|null $user
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Address> $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Segment> $segments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Wishlist> $wishlists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerNote> $notes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerGroup> $groups
 */
class Customer extends Model implements HasMedia
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasTags;
    use HasUuids;
    use InteractsWithMedia;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'customers.features.owner';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => CustomerStatus::class,
        'wallet_balance' => 'integer',
        'lifetime_value' => 'integer',
        'total_orders' => 'integer',
        'email_verified_at' => 'datetime',
        'last_order_at' => 'datetime',
        'last_login_at' => 'datetime',
        'accepts_marketing' => 'boolean',
        'is_tax_exempt' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CustomerCreated::class,
        'updated' => CustomerUpdated::class,
    ];

    public function getTable(): string
    {
        return config('customers.database.tables.customers', 'customers');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the associated user.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model>|null $userModel */
        $userModel = config('customers.integrations.user_model');

        /** @var class-string<Model>|null $fallbackUserModel */
        $fallbackUserModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel ?? $fallbackUserModel ?? \Illuminate\Foundation\Auth\User::class, 'user_id');
    }

    /**
     * Get the customer's addresses.
     *
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'customer_id');
    }

    /**
     * Get the customer's segments.
     *
     * @return BelongsToMany<Segment, $this>
     */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(
            Segment::class,
            config('customers.database.tables.segment_customer', 'customer_segment_customer'),
            'customer_id',
            'segment_id'
        )->withTimestamps();
    }

    /**
     * Get the customer's wishlists.
     *
     * @return HasMany<Wishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'customer_id');
    }

    /**
     * Get the customer's notes.
     *
     * @return HasMany<CustomerNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class, 'customer_id')->latest();
    }

    /**
     * Get the customer's group memberships.
     *
     * @return BelongsToMany<CustomerGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerGroup::class,
            config('customers.database.tables.group_members', 'customer_group_members'),
            'customer_id',
            'group_id'
        )->withPivot(['role', 'joined_at'])->withTimestamps();
    }

    // =========================================================================
    // ADDRESS HELPERS
    // =========================================================================

    /**
     * Get the default billing address.
     */
    public function getDefaultBillingAddress(): ?Address
    {
        return $this->addresses()
            ->where('is_default_billing', true)
            ->first();
    }

    /**
     * Get the default shipping address.
     */
    public function getDefaultShippingAddress(): ?Address
    {
        return $this->addresses()
            ->where('is_default_shipping', true)
            ->first();
    }

    // =========================================================================
    // WALLET HELPERS
    // =========================================================================

    /**
     * Get the formatted wallet balance.
     */
    public function getFormattedWalletBalance(): string
    {
        $currency = config('customers.defaults.wallet.currency', 'MYR');

        return Money::$currency($this->wallet_balance, true)->format();
    }

    /**
     * Add credit to wallet.
     */
    public function addCredit(int $amountInCents, ?string $reason = null): bool
    {
        if (! config('customers.features.wallet.enabled', true)) {
            return false;
        }

        if ($amountInCents <= 0) {
            return false;
        }

        $minTopup = (int) config('customers.defaults.wallet.min_topup', 0);
        if ($minTopup > 0 && $amountInCents < $minTopup) {
            return false;
        }

        $maxBalance = config('customers.defaults.wallet.max_balance', 100000_00);

        if (($this->wallet_balance + $amountInCents) > $maxBalance) {
            return false;
        }

        $this->increment('wallet_balance', $amountInCents);

        event(new WalletCreditAdded($this, $amountInCents, $reason));

        return true;
    }

    /**
     * Deduct credit from wallet.
     */
    public function deductCredit(int $amountInCents, ?string $reason = null): bool
    {
        if (! config('customers.features.wallet.enabled', true)) {
            return false;
        }

        if ($amountInCents <= 0) {
            return false;
        }

        if ($this->wallet_balance < $amountInCents) {
            return false;
        }

        $this->decrement('wallet_balance', $amountInCents);

        event(new WalletCreditDeducted($this, $amountInCents, $reason));

        return true;
    }

    /**
     * Check if customer has sufficient wallet balance.
     */
    public function hasWalletBalance(int $amountInCents): bool
    {
        return $this->wallet_balance >= $amountInCents;
    }

    // =========================================================================
    // LTV HELPERS
    // =========================================================================

    /**
     * Get the formatted lifetime value.
     */
    public function getFormattedLifetimeValue(): string
    {
        $currency = config('customers.defaults.wallet.currency', 'MYR');

        return Money::$currency($this->lifetime_value, true)->format();
    }

    /**
     * Get the average order value.
     */
    public function getAverageOrderValue(): int
    {
        if ($this->total_orders === 0) {
            return 0;
        }

        return (int) ($this->lifetime_value / $this->total_orders);
    }

    /**
     * Record a new order.
     */
    public function recordOrder(int $orderValueInCents): void
    {
        $this->increment('total_orders');
        $this->increment('lifetime_value', $orderValueInCents);
        $this->update(['last_order_at' => now()]);
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === CustomerStatus::Suspended;
    }

    public function canPlaceOrders(): bool
    {
        return $this->status->canPlaceOrders();
    }

    // =========================================================================
    // MARKETING HELPERS
    // =========================================================================

    public function acceptsMarketing(): bool
    {
        return $this->accepts_marketing;
    }

    public function optInMarketing(): void
    {
        $this->update(['accepts_marketing' => true]);
    }

    public function optOutMarketing(): void
    {
        $this->update(['accepts_marketing' => false]);
    }

    // =========================================================================
    // FULL NAME
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        return mb_trim("{$this->first_name} {$this->last_name}");
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAcceptsMarketing(Builder $query): Builder
    {
        return $query->where('accepts_marketing', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHighValue(Builder $query, int $minLifetimeValue = 1000_00): Builder
    {
        return $query->where('lifetime_value', '>=', $minLifetimeValue);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInSegment(Builder $query, string | Segment $segment): Builder
    {
        $segmentId = $segment instanceof Segment ? $segment->id : $segment;

        return $query->whereHas('segments', fn ($q) => $q->where('segment_id', $segmentId));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRecentlyActive(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }

    // =========================================================================
    // MEDIA COLLECTIONS
    // =========================================================================

    /**
     * Register media collections for customer files.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('documents');
    }

    /**
     * Get the customer's avatar URL.
     */
    public function getAvatarUrl(?string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('avatar');

        return $media?->getUrl($conversion);
    }

    // =========================================================================
    // TAG HELPERS
    // =========================================================================

    /**
     * Tag the customer for segmentation.
     *
     * @param  array<int, string>|string  $tags
     */
    public function tagForSegment(array | string $tags): static
    {
        $this->attachTags($tags, 'segments');

        return $this;
    }

    /**
     * Get customers in a specific segment tag.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithSegmentTag(Builder $query, string $tag): Builder
    {
        return $query->withAnyTags([$tag], 'segments');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (! (bool) config('customers.features.owner.enabled', false)) {
                return;
            }

            if ($customer->owner_id !== null) {
                return;
            }

            if (! (bool) config('customers.features.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = \AIArmada\CommerceSupport\Support\OwnerContext::resolve();

            if ($owner !== null) {
                $customer->assignOwner($owner);
            }
        });

        static::deleting(function (Customer $customer): void {
            $customer->addresses()->delete();
            $customer->wishlists()->delete();
            $customer->notes()->delete();
            $customer->segments()->detach();
            $customer->groups()->detach();
        });
    }

    // =========================================================================
    // ACTIVITY LOGGING
    // =========================================================================

    /**
     * Get the attributes to log for activity tracking.
     *
     * @return array<int, string>
     */
    protected function getLoggableAttributes(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
            'phone',
            'status',
            'wallet_balance',
            'accepts_marketing',
            'is_tax_exempt',
        ];
    }

    /**
     * Get the activity log name for categorization.
     */
    protected function getActivityLogName(): string
    {
        return 'customers';
    }
}
