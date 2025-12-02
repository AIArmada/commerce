<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class CartItem implements Arrayable, Jsonable, JsonSerializable, LineItemInterface
{
    use Traits\AssociatedModelTrait;
    use Traits\AttributeTrait;
    use Traits\ConditionTrait;
    use Traits\MoneyTrait;
    use Traits\SerializationTrait;
    use Traits\ValidationTrait;

    public string $id;

    public CartConditionCollection $conditions;

    /** @var Collection<string, mixed> */
    public Collection $attributes;

    public int $price;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|Collection<string, CartCondition>  $conditions
     */
    public function __construct(
        string|int $id,
        public string $name,
        int|float|string $price,
        public int $quantity,
        array $attributes = [],
        /** @var array|Collection<string, CartCondition> */ array|Collection $conditions = [],
        public string|object|null $associatedModel = null
    ) {
        // Normalize ID to string for consistent handling
        $this->id = (string) $id;

        $this->attributes = new Collection($attributes);
        $this->conditions = $this->normalizeConditions($conditions);

        // Store price as integer cents
        $this->price = $this->normalizeToInt($price);

        $this->validateCartItem();
    }

    /**
     * Set item quantity
     */
    public function setQuantity(int $quantity): static
    {
        if ($quantity < 1) {
            throw new \AIArmada\Cart\Exceptions\InvalidCartItemException('Quantity must be at least 1');
        }

        return new self(
            $this->id,
            $this->name,
            $this->price,
            $quantity,
            $this->attributes->toArray(),
            $this->conditions->toArray(),
            $this->associatedModel
        );
    }

    /**
     * Check if two cart items are equal
     */
    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    /**
     * Create a copy of the item with modified properties
     *
     * @param  array<string, mixed>  $attributes
     */
    public function with(array $attributes): static
    {
        return new static(
            $attributes['id'] ?? $this->id,
            $attributes['name'] ?? $this->name,
            $attributes['price'] ?? $this->price,
            $attributes['quantity'] ?? $this->quantity,
            $attributes['attributes'] ?? $this->attributes->toArray(),
            $attributes['conditions'] ?? $this->conditions->toArray(),
            $attributes['associated_model'] ?? $this->associatedModel
        );
    }

    /**
     * Normalize price to integer cents.
     *
     * @param  int|float|string  $price  Price input
     *                                   - int: treated as cents (returned as-is)
     *                                   - float: treated as decimal dollars, converted to cents
     *                                   - string: sanitized and converted
     */
    private function normalizeToInt(int|float|string $price): int
    {
        if (is_int($price)) {
            return $price;
        }

        if (is_float($price)) {
            // Float is treated as decimal dollars, convert to cents
            return (int) round($price * 100);
        }

        // String handling
        return $this->sanitizeStringPrice($price);
    }

    /**
     * Sanitize string price input and convert to integer cents.
     */
    private function sanitizeStringPrice(string $price): int
    {
        // Remove currency symbols and formatting
        $price = str_replace([',', '$', '€', '£', '¥', '₹', 'RM', ' '], '', $price);

        // If contains decimal, assume it's in major units (e.g., 99.99) and convert to cents
        if (str_contains($price, '.')) {
            return (int) round((float) $price * 100);
        }

        // Otherwise assume it's already in cents
        return (int) $price;
    }
}
