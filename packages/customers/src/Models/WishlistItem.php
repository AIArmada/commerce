<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $wishlist_id
 * @property string $product_type
 * @property string $product_id
 * @property \Carbon\CarbonInterface|null $added_at
 * @property bool $notified_on_sale
 * @property bool $notified_in_stock
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read Wishlist $wishlist
 * @property-read Model|null $product
 */
class WishlistItem extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'customers.features.owner';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'added_at' => 'datetime',
        'notified_on_sale' => 'boolean',
        'notified_in_stock' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notified_on_sale' => false,
        'notified_in_stock' => false,
    ];

    public function getTable(): string
    {
        return config('customers.database.tables.wishlist_items', 'wishlist_items');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the wishlist this item belongs to.
     *
     * @return BelongsTo<Wishlist, $this>
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class, 'wishlist_id');
    }

    /**
     * Get the product (polymorphic).
     */
    public function product(): MorphTo
    {
        return $this->morphTo('product', 'product_type', 'product_id');
    }

    // =========================================================================
    // NOTIFICATION HELPERS
    // =========================================================================

    /**
     * Mark that the customer was notified about a sale.
     */
    public function markSaleNotified(): void
    {
        $this->update(['notified_on_sale' => true]);
    }

    /**
     * Mark that the customer was notified about stock.
     */
    public function markStockNotified(): void
    {
        $this->update(['notified_in_stock' => true]);
    }

    /**
     * Reset notification flags (e.g., when price changes again).
     */
    public function resetNotifications(): void
    {
        $this->update([
            'notified_on_sale' => false,
            'notified_in_stock' => false,
        ]);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNeedsStockNotification(Builder $query): Builder
    {
        return $query->where('notified_in_stock', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNeedsSaleNotification(Builder $query): Builder
    {
        return $query->where('notified_on_sale', false);
    }

    protected static function booted(): void
    {
        static::creating(function (WishlistItem $item): void {
            if ((bool) config('customers.features.owner.enabled', false)) {
                if ($item->owner_type === null && $item->owner_id === null) {
                    $owner = OwnerContext::resolve();

                    $wishlist = Wishlist::query()
                        ->forOwner($owner, includeGlobal: false)
                        ->whereKey($item->wishlist_id)
                        ->first();

                    if ($wishlist === null) {
                        throw new InvalidArgumentException('Wishlist item wishlist must belong to the current owner context.');
                    }

                    if ($wishlist->owner_type !== null && $wishlist->owner_id !== null) {
                        $item->owner_type = $wishlist->owner_type;
                        $item->owner_id = $wishlist->owner_id;
                    }
                }
            }

            if (empty($item->added_at)) {
                $item->added_at = now();
            }
        });

        static::updating(function (WishlistItem $item): void {
            if (! (bool) config('customers.features.owner.enabled', false)) {
                return;
            }

            if (! $item->isDirty('wishlist_id')) {
                return;
            }

            $owner = OwnerContext::resolve();

            $wishlist = Wishlist::query()
                ->forOwner($owner, includeGlobal: false)
                ->whereKey($item->wishlist_id)
                ->first();

            if ($wishlist === null) {
                throw new InvalidArgumentException('Wishlist item wishlist must belong to the current owner context.');
            }

            $item->owner_type = $wishlist->owner_type;
            $item->owner_id = $wishlist->owner_id;
        });
    }
}
