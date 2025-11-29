<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Models\CartItem;

trait ManagesStorage
{
    /**
     * Get all cart items
     */
    public function getItems(): CartCollection
    {
        $items = $this->storage->getItems($this->getIdentifier(), $this->instance());

        if (! $items || ! is_array($items)) { // @phpstan-ignore function.alreadyNarrowedType
            return new CartCollection;
        }

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
                    (float) $itemData['price'], // Ensure price is float
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
    private function restoreAssociatedModel(mixed $associatedData): object|string|null
    {
        if (is_string($associatedData)) {
            return $associatedData;
        }

        if (is_array($associatedData) && isset($associatedData['class'])) {
            // Return just the class name if it exists, otherwise null
            $className = $associatedData['class'];
            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }
}
