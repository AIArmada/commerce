<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

trait ManagesStorage
{
    /**
     * Per-cart resolved associated models, keyed by "class:id".
     *
     * The Cart object is request-scoped, so this cache never leaks across requests.
     *
     * @var array<string, object|string|null>
     */
    private array $associatedModelCache = [];

    /**
     * Get all cart items (with dynamic condition evaluation)
     */
    public function getItems(): CartCollection
    {
        // Ensure dynamic conditions are evaluated before returning items
        // This is necessary for item-level dynamic conditions to be applied
        $this->evaluateDynamicConditionsIfDirty();

        return $this->getItemsFromStorage();
    }

    /**
     * Get items directly from storage (no dynamic condition evaluation)
     *
     * Used internally to avoid infinite recursion when evaluating
     * dynamic conditions that need access to item data.
     */
    protected function getItemsFromStorage(): CartCollection
    {
        $items = $this->storage->getItems($this->getIdentifier(), $this->instance());

        // Batch-resolve associated models: one query per model class instead of
        // one unscoped query per item.
        $this->warmAssociatedModelCache($items);

        // Convert array back to CartCollection
        $collection = new CartCollection;
        foreach ($items as $itemData) {
            if (is_array($itemData) && isset($itemData['id'])) {
                // Handle associated model restoration
                $associatedModel = null;
                if (isset($itemData['associated_model'])) {
                    $associatedModel = $this->restoreAssociatedModel($itemData['associated_model']);
                }

                $item = new CartItem(
                    $itemData['id'],
                    $itemData['name'],
                    (int) $itemData['price'], // Ensure price is int (cents)
                    $itemData['quantity'],
                    $itemData['attributes'] ?? [],
                    $itemData['conditions'] ?? [],
                    $associatedModel
                );
                $collection->put($item->id, $item);
            }
        }

        return $collection;
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return $this->getItems()->isEmpty();
    }

    /**
     * Get complete cart content (items, conditions, totals, etc.)
     * Includes all database columns for complete snapshots and auditing
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return [
            'id' => $this->getId(),
            'identifier' => $this->getIdentifier(),
            'instance' => $this->instanceName,
            'version' => $this->getVersion(),
            'metadata' => $this->storage->getAllMetadata($this->getIdentifier(), $this->instance()),
            'items' => $this->getItems()->toArray(),
            'conditions' => $this->getConditions()->toArray(),
            'subtotal' => $this->getRawSubtotal(),
            'total' => $this->getRawTotal(),
            'quantity' => $this->getTotalQuantity(),
            'count' => $this->countItems(), // Number of unique items, not total quantity
            'is_empty' => $this->isEmpty(),
            'created_at' => $this->storage->getCreatedAt($this->getIdentifier(), $this->instance()),
            'updated_at' => $this->storage->getUpdatedAt($this->getIdentifier(), $this->instance()),
        ];
    }

    /**
     * Get complete cart content (alias for content())
     *
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content();
    }

    /**
     * Convert cart to array (alias for content())
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->content();
    }

    /**
     * Save cart items to storage
     */
    private function save(CartCollection $items): void
    {
        $itemsArray = $items->toArray();
        $this->storage->putItems($this->getIdentifier(), $this->instance(), $itemsArray);
    }

    /**
     * Restore associated model from array format
     */
    private function restoreAssociatedModel(mixed $associatedData): object | string | null
    {
        if (is_string($associatedData)) {
            return $associatedData;
        }

        if (is_array($associatedData) && isset($associatedData['class'])) {
            $className = $associatedData['class'];

            if (! is_string($className) || ! class_exists($className)) {
                return null;
            }

            // If we have an ID and the class is an Eloquent model, fetch it
            if (isset($associatedData['id']) && is_scalar($associatedData['id']) && is_subclass_of($className, Model::class)) {
                $cacheKey = $className . ':' . $associatedData['id'];

                if (array_key_exists($cacheKey, $this->associatedModelCache)) {
                    return $this->associatedModelCache[$cacheKey];
                }

                try {
                    $model = $this->scopedAssociatedQuery($className, [$associatedData['id']])->first();
                    $this->associatedModelCache[$cacheKey] = $model;

                    return $model;
                } catch (Exception) {
                    // If fetch fails, return just the class name
                    return $className;
                }
            }

            // Fallback to just returning the class name
            return $className;
        }

        return null;
    }

    /**
     * Preload every associated model referenced by the given stored items with
     * one query per model class.
     *
     * @param  array<string, mixed>  $items
     */
    private function warmAssociatedModelCache(array $items): void
    {
        /** @var array<string, array<string, string|int>> $idsByClass */
        $idsByClass = [];

        foreach ($items as $itemData) {
            if (! is_array($itemData) || ! is_array($itemData['associated_model'] ?? null)) {
                continue;
            }

            $associatedData = $itemData['associated_model'];
            $className = $associatedData['class'] ?? null;
            $id = $associatedData['id'] ?? null;

            if (! is_string($className) || ! class_exists($className) || ! is_subclass_of($className, Model::class)) {
                continue;
            }

            if (! is_scalar($id)) {
                continue;
            }

            $cacheKey = $className . ':' . $id;

            if (array_key_exists($cacheKey, $this->associatedModelCache)) {
                continue;
            }

            $idsByClass[$className][$cacheKey] = $id;
        }

        foreach ($idsByClass as $className => $keyedIds) {
            try {
                $models = $this->scopedAssociatedQuery($className, array_values(array_unique($keyedIds)))->get();
            } catch (Exception) {
                continue;
            }

            $found = [];

            foreach ($models as $model) {
                $found[(string) $model->getKey()] = $model;
            }

            foreach ($keyedIds as $cacheKey => $id) {
                $this->associatedModelCache[$cacheKey] = $found[(string) $id] ?? null;
            }
        }
    }

    /**
     * Build an owner-scoped lookup for associated models of one class.
     *
     * Models using the shared HasOwner trait are constrained to the cart
     * storage's owner tuple (or to global-only rows for global carts) so a
     * stored class+id pair can never resolve another owner's row.
     *
     * @param  array<int, string|int>  $ids
     * @return EloquentBuilder<Model>
     */
    private function scopedAssociatedQuery(string $className, array $ids): EloquentBuilder
    {
        /** @var Model $prototype */
        $prototype = new $className;

        $query = $prototype->newQuery()->whereIn($prototype->getKeyName(), $ids);

        if (! (bool) config('cart.owner.enabled', false)) {
            return $query;
        }

        if (! in_array(HasOwner::class, class_uses_recursive($className), true)) {
            return $query;
        }

        $ownerTypeColumn = 'owner_type';
        $ownerIdColumn = 'owner_id';

        if (is_a($className, OwnerScopeConfigurable::class, true)) {
            $scopeConfig = $className::ownerScopeConfig();

            if (! $scopeConfig->enabled) {
                return $query;
            }

            $ownerTypeColumn = $scopeConfig->ownerTypeColumn;
            $ownerIdColumn = $scopeConfig->ownerIdColumn;
        }

        try {
            $owner = OwnerContext::fromTypeAndId($this->storage->getOwnerType(), $this->storage->getOwnerId());
        } catch (Exception) {
            return $query;
        }

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            (bool) config('cart.owner.include_global', false),
            $ownerTypeColumn,
            $ownerIdColumn,
        );
    }
}
