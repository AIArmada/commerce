<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Contracts\BuyableInterface;
use AIArmada\Cart\Exceptions\ProductNotPurchasableException;
use AIArmada\Cart\Models\CartItem;

/**
 * Trait for adding Buyable products to the cart with validation.
 *
 * This trait provides methods to add products that implement BuyableInterface,
 * with automatic validation for stock, quantity limits, and purchasability.
 */
trait ManagesBuyables
{
    /**
     * Add a Buyable product to the cart with validation.
     *
     * @param  BuyableInterface  $buyable  The product to add
     * @param  int  $quantity  Quantity to add
     * @param  array<string, mixed>  $extraAttributes  Additional attributes to merge
     *
     * @throws ProductNotPurchasableException If product cannot be purchased
     */
    public function addBuyable(
        BuyableInterface $buyable,
        int $quantity = 1,
        array $extraAttributes = []
    ): CartItem {
        // Validate the product can be purchased
        $this->validateBuyable($buyable, $quantity);

        // Merge buyable attributes with extra attributes
        $attributes = array_merge(
            $buyable->getBuyableAttributes(),
            $extraAttributes
        );

        // Add to cart
        return $this->add(
            id: $buyable->getBuyableIdentifier(),
            name: $buyable->getBuyableName(),
            price: $buyable->getBuyablePrice()->getAmount(),
            quantity: $quantity,
            attributes: $attributes,
            associatedModel: $buyable
        );
    }

    /**
     * Update a Buyable product quantity with validation.
     *
     * @param  BuyableInterface  $buyable  The product to update
     * @param  int  $newQuantity  New absolute quantity
     *
     * @throws ProductNotPurchasableException If new quantity is not valid
     */
    public function updateBuyable(BuyableInterface $buyable, int $newQuantity): ?CartItem
    {
        if ($newQuantity <= 0) {
            return $this->remove($buyable->getBuyableIdentifier());
        }

        // Validate the new quantity
        $this->validateBuyable($buyable, $newQuantity);

        return $this->update($buyable->getBuyableIdentifier(), [
            'quantity' => ['value' => $newQuantity],
        ]);
    }

    /**
     * Remove a Buyable product from the cart.
     */
    public function removeBuyable(BuyableInterface $buyable): ?CartItem
    {
        return $this->remove($buyable->getBuyableIdentifier());
    }

    /**
     * Check if a Buyable product is in the cart.
     */
    public function hasBuyable(BuyableInterface $buyable): bool
    {
        return $this->has($buyable->getBuyableIdentifier());
    }

    /**
     * Get the cart item for a Buyable product.
     */
    public function getBuyable(BuyableInterface $buyable): ?CartItem
    {
        return $this->get($buyable->getBuyableIdentifier());
    }

    /**
     * Validate that a Buyable can be purchased with the given quantity.
     *
     * @throws ProductNotPurchasableException If validation fails
     */
    public function validateBuyable(BuyableInterface $buyable, int $quantity): void
    {
        $productId = $buyable->getBuyableIdentifier();
        $productName = $buyable->getBuyableName();

        // Check if product can be purchased at all
        if (! $buyable->canBePurchased($quantity)) {
            // Determine specific reason
            $stock = $buyable->getBuyableStock();

            if ($stock !== null && $stock < $quantity) {
                throw ProductNotPurchasableException::outOfStock(
                    $productId,
                    $productName,
                    $quantity,
                    $stock
                );
            }

            throw ProductNotPurchasableException::inactive($productId, $productName);
        }

        // Check minimum quantity
        $minQty = $buyable->getMinimumQuantity();
        if ($quantity < $minQty) {
            throw ProductNotPurchasableException::minimumNotMet(
                $productId,
                $productName,
                $quantity,
                $minQty
            );
        }

        // Check maximum quantity
        $maxQty = $buyable->getMaximumQuantity();
        if ($maxQty !== null && $quantity > $maxQty) {
            throw ProductNotPurchasableException::maximumExceeded(
                $productId,
                $productName,
                $quantity,
                $maxQty
            );
        }

        // Check quantity increment
        $increment = $buyable->getQuantityIncrement();
        if ($increment > 1 && ($quantity % $increment) !== 0) {
            throw ProductNotPurchasableException::invalidIncrement(
                $productId,
                $productName,
                $quantity,
                $increment
            );
        }
    }

    /**
     * Refresh prices for all Buyable items in the cart.
     *
     * This is useful when prices may have changed (e.g., flash sale started).
     *
     * @param  callable(BuyableInterface): BuyableInterface|null  $resolver  Resolves cart item to fresh Buyable
     * @return array<string, array{old: int, new: int}> Price changes by item ID
     */
    public function refreshBuyablePrices(callable $resolver): array
    {
        $changes = [];

        foreach ($this->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof BuyableInterface) {
                continue;
            }

            // Get fresh instance
            $fresh = $resolver($model);

            if ($fresh === null) {
                continue;
            }

            $oldPrice = (int) $item->price;
            $newPrice = $fresh->getBuyablePrice()->getAmount();

            if ($oldPrice !== $newPrice) {
                $this->update($item->id, ['price' => $newPrice]);
                $changes[$item->id] = ['old' => $oldPrice, 'new' => $newPrice];
            }
        }

        return $changes;
    }

    /**
     * Validate all Buyable items in the cart are still purchasable.
     *
     * @param  callable(BuyableInterface): BuyableInterface|null  $resolver  Resolves cart item to fresh Buyable
     * @return array<string, ProductNotPurchasableException> Validation errors by item ID
     */
    public function validateAllBuyables(callable $resolver): array
    {
        $errors = [];

        foreach ($this->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof BuyableInterface) {
                continue;
            }

            // Get fresh instance
            $fresh = $resolver($model);

            if ($fresh === null) {
                $errors[$item->id] = ProductNotPurchasableException::inactive(
                    $item->id,
                    $item->name
                );

                continue;
            }

            try {
                $this->validateBuyable($fresh, $item->quantity);
            } catch (ProductNotPurchasableException $e) {
                $errors[$item->id] = $e;
            }
        }

        return $errors;
    }

    /**
     * Get total weight of all items in the cart (for shipping).
     *
     * @return int Total weight in grams
     */
    public function getTotalWeight(): int
    {
        $totalWeight = 0;

        foreach ($this->getItems() as $item) {
            $weight = $item->getAttribute('weight');

            if ($weight !== null) {
                $totalWeight += (int) $weight * $item->quantity;
            }
        }

        return $totalWeight;
    }
}
