<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collections;

use AIArmada\Cart\Models\CartItem;
use Illuminate\Support\Collection;

final class CartCollection extends Collection
{
    /**
     * Add a cart item to the collection
     */
    public function addItem(CartItem $item): static
    {
        return $this->put($item->id, $item);
    }

    /**
     * Remove a cart item from the collection
     */
    public function removeItem(string $id): static
    {
        return $this->forget($id);
    }

    /**
     * Get cart item by ID
     */
    public function getItem(string $id): ?CartItem
    {
        return $this->get($id);
    }

    /**
     * Check if cart item exists
     */
    public function hasItem(string $id): bool
    {
        return $this->has($id);
    }

    /**
     * Get total quantity of all items
     */
    public function getTotalQuantity(): int
    {
        return $this->sum('quantity');
    }

    /**
     * Get subtotal of all items (with item-level conditions applied)
     */
    public function subtotal(): mixed
    {
        // For collection, we don't have formatting config, so just return the raw value
        // Individual Cart instances would handle formatting
        return $this->getSubtotal();
    }

    /**
     * Get subtotal without any conditions (raw base prices)
     */
    public function subtotalWithoutConditions(): mixed
    {
        return $this->getSubtotalWithoutConditions();
    }

    /**
     * Get total (alias for subtotal since collections only have item-level conditions)
     */
    public function total(): mixed
    {
        return $this->subtotal();
    }

    /**
     * Get total without any conditions (alias for subtotalWithoutConditions)
     */
    public function totalWithoutConditions(): mixed
    {
        return $this->subtotalWithoutConditions();
    }

    /**
     * Convert collection to array with formatted output
     *
     * @return array<string, mixed>
     */
    public function toFormattedArray(): array
    {
        return [
            'items' => $this->map(fn (CartItem $item) => $item->toArray())->toArray(),
            'total_quantity' => $this->getTotalQuantity(),
            'subtotal' => $this->getSubtotal(),
            'total' => $this->getSubtotal(),
            'total_without_conditions' => $this->getSubtotalWithoutConditions(),
            'count' => $this->count(),
            'is_empty' => $this->isEmpty(),
        ];
    }

    /**
     * Filter items by a condition
     */
    public function filterByCondition(string $conditionName): static
    {
        return $this->filter(fn (CartItem $item) => $item->hasCondition($conditionName));
    }

    /**
     * Filter items by condition type
     */
    public function filterByConditionType(string $type): static
    {
        return $this->filter(function (CartItem $item) use ($type) {
            return $item->getConditions()->contains(fn ($condition) => $condition->getType() === $type);
        });
    }

    /**
     * Filter items by condition target
     */
    public function filterByConditionTarget(string $target): static
    {
        return $this->filter(function (CartItem $item) use ($target) {
            return $item->getConditions()->contains(fn ($condition) => $condition->getTarget() === $target);
        });
    }

    /**
     * Filter items by condition value
     */
    public function filterByConditionValue(string|float $value): static
    {
        return $this->filter(function (CartItem $item) use ($value) {
            return $item->getConditions()->contains(fn ($condition) => $condition->getValue() === $value);
        });
    }

    /**
     * Get items with specific attribute
     */
    public function filterByAttribute(string $attribute, mixed $value = null): static
    {
        return $this->filter(function (CartItem $item) use ($attribute, $value) {
            if ($value === null) {
                return $item->hasAttribute($attribute);
            }

            return $item->getAttribute($attribute) === $value;
        });
    }

    /**
     * Get items associated with a specific model
     */
    public function filterByModel(string $modelClass): static
    {
        return $this->filter(fn (CartItem $item) => $item->isAssociatedWith($modelClass));
    }

    /**
     * Search items by name
     */
    public function searchByName(string $query): static
    {
        return $this->filter(fn (CartItem $item) => str_contains(
            mb_strtolower($item->name),
            mb_strtolower($query)
        ));
    }

    /**
     * Sort items by price
     */
    public function sortByPrice(string $direction = 'asc'): static
    {
        return $direction === 'desc'
            ? $this->sortByDesc('price')
            : $this->sortBy('price');
    }

    /**
     * Sort items by quantity
     */
    public function sortByQuantity(string $direction = 'asc'): static
    {
        return $direction === 'desc'
            ? $this->sortByDesc('quantity')
            : $this->sortBy('quantity');
    }

    /**
     * Sort items by name
     */
    public function sortByName(string $direction = 'asc'): static
    {
        return $direction === 'desc'
            ? $this->sortByDesc('name')
            : $this->sortBy('name');
    }

    /**
     * Get unique items by a specific property
     */
    public function uniqueBy(string $property): static
    {
        return $this->unique($property);
    }

    /**
     * Group items by a specific property
     *
     * @return Collection<string, static>
     */
    public function groupByProperty(string $property): Collection
    {
        return $this->groupBy($property);
    }

    /**
     * Get items with quantity greater than specified amount
     */
    public function whereQuantityGreaterThan(int $quantity): static
    {
        return $this->filter(fn (CartItem $item) => $item->quantity > $quantity);
    }

    /**
     * Get items with quantity less than specified amount
     */
    public function whereQuantityLessThan(int $quantity): static
    {
        return $this->filter(fn (CartItem $item) => $item->quantity < $quantity);
    }

    /**
     * Get items with price between range
     */
    public function wherePriceBetween(float $min, float $max): static
    {
        return $this->filter(fn (CartItem $item) => $item->price >= $min && $item->price <= $max);
    }

    /**
     * Check if collection contains items with conditions
     */
    public function hasItemsWithConditions(): bool
    {
        return $this->contains(fn (CartItem $item) => $item->hasConditions());
    }

    /**
     * Get total discount amount from all items
     */
    public function getTotalDiscount(): float
    {
        return $this->sum(function (CartItem $item) {
            $discount = $item->getDiscountAmount();

            return $discount->getAmount();
        });
    }

    /**
     * Get statistics about the cart
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'total_items' => $this->count(),
            'total_quantity' => $this->getTotalQuantity(),
            'average_price' => $this->avg('price'),
            'highest_price' => $this->max('price'),
            'lowest_price' => $this->min('price'),
            'total_value' => $this->getSubtotal(),
            'total_with_conditions' => $this->getSubtotal(), // Same as total_value since subtotal now includes conditions
            'items_with_conditions' => $this->filter(fn (CartItem $item) => $item->hasConditions())->count(),
        ];
    }

    /**
     * Get items where quantity is above threshold
     */
    public function whereQuantityAbove(int $threshold): static
    {
        return $this->filter(fn (CartItem $item) => $item->quantity > $threshold);
    }

    /**
     * Group items by attribute value
     *
     * @return Collection<string, static>
     */
    public function groupByAttribute(string $attribute): Collection
    {
        return $this->groupBy(fn (CartItem $item) => $item->getAttribute($attribute));
    }

    /**
     * Find items by model type (if associated)
     */
    public function whereModel(string $modelClass): static
    {
        return $this->filter(fn (CartItem $item) => $item->isAssociatedWith($modelClass));
    }

    /**
     * Get subtotal of all items with item-level conditions applied (raw value)
     *
     * @internal For internal calculations only
     */
    protected function getSubtotal(): float
    {
        return $this->sum(fn ($item) => $item->getRawSubtotal());
    }

    /**
     * Get subtotal of all items without any conditions (raw value)
     *
     * @internal For internal calculations only
     */
    protected function getSubtotalWithoutConditions(): float
    {
        return $this->sum(fn ($item) => $item->getRawSubtotalWithoutConditions());
    }
}
