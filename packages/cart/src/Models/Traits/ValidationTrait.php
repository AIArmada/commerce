<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\UnknownModelException;
use JsonException;

trait ValidationTrait
{
    /**
     * Validate cart item properties
     */
    private function validateCartItem(): void
    {
        if (empty(mb_trim($this->id))) {
            throw new InvalidCartItemException('Cart item ID cannot be empty');
        }
        if (empty(mb_trim($this->name))) {
            throw new InvalidCartItemException('Cart item name cannot be empty');
        }
        // Check string length limits
        $maxStringLength = config('cart.limits.max_string_length', 255);
        if (mb_strlen($this->id) > $maxStringLength) {
            throw new InvalidCartItemException("Cart item ID cannot exceed {$maxStringLength} characters");
        }
        if (mb_strlen($this->name) > $maxStringLength) {
            throw new InvalidCartItemException("Cart item name cannot exceed {$maxStringLength} characters");
        }
        if ($this->price < 0) {
            throw new InvalidCartItemException('Cart item price cannot be negative');
        }
        if ($this->quantity < 1) {
            throw new InvalidCartItemException('Cart item quantity must be at least 1');
        }
        // Check quantity limits
        $maxQuantity = config('cart.limits.max_item_quantity', 10000);
        if ($this->quantity > $maxQuantity) {
            throw new InvalidCartItemException("Cart item quantity cannot exceed {$maxQuantity}");
        }
        if (is_string($this->associatedModel) && ! empty($this->associatedModel)) {
            if (! class_exists($this->associatedModel)) {
                throw new UnknownModelException("Model class '{$this->associatedModel}' does not exist");
            }
        }
        // Validate attributes size
        if (! empty($this->attributes)) {
            $attributesArray = $this->attributes->toArray();
            $this->validateDataSize($attributesArray, 'item attributes');
        }
    }

    /**
     * Validate data size to prevent memory issues
     *
     * @param  array<string, mixed>  $data
     */
    private function validateDataSize(array $data, string $type): void
    {
        $maxDataSize = config('cart.limits.max_data_size_bytes', 1024 * 1024); // 1MB default
        try {
            $jsonSize = mb_strlen(json_encode($data, JSON_THROW_ON_ERROR));
            if ($jsonSize > $maxDataSize) {
                $maxSizeMB = round($maxDataSize / (1024 * 1024), 2);
                throw new InvalidCartItemException("Cart item {$type} data size ({$jsonSize} bytes) exceeds maximum allowed size of {$maxSizeMB}MB");
            }
        } catch (JsonException $e) {
            throw new InvalidCartItemException("Cannot validate {$type} data size: ".$e->getMessage());
        }
    }
}
