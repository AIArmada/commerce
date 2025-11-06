<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class CartItem implements Arrayable, Jsonable, JsonSerializable
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

    public float|int $price;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|Collection<string, CartCondition>  $conditions
     */
    public function __construct(
        string|int $id,
        public string $name,
        float|int|string $price,
        public int $quantity,
        array $attributes = [],
        /** @var array|Collection<string, CartCondition> */ array|Collection $conditions = [],
        public string|object|null $associatedModel = null
    ) {
        // Normalize ID to string for consistent handling
        $this->id = (string) $id;

        $this->attributes = new Collection($attributes);
        $this->conditions = $this->normalizeConditions($conditions);

        // Store raw price as-is (no transformation)
        $this->price = is_string($price) ? $this->sanitizeStringPrice($price) : $price;

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
     * Sanitize string price input
     */
    private function sanitizeStringPrice(string $price): float|int
    {
        // Only sanitize string input - no transformation
        $price = str_replace([',', '$', '€', '£', '¥', '₹', 'RM', ' '], '', $price);

        return str_contains($price, '.') ? (float) $price : (int) $price;
    }

    /**
     * Normalize conditions to CartConditionCollection
     *
     * @param  array<string, mixed>|Collection<string, CartCondition>  $conditions
     */
    private function normalizeConditions(array|Collection $conditions): CartConditionCollection
    {
        if ($conditions instanceof CartConditionCollection) {
            return $conditions;
        }

        $collection = new CartConditionCollection;

        if (is_array($conditions)) {
            foreach ($conditions as $key => $condition) {
                if ($condition instanceof CartCondition) {
                    $collection->put($condition->getName(), $condition);
                } elseif (is_array($condition)) {
                    $collection->put($key, CartCondition::fromArray($condition));
                }
            }
        } elseif ($conditions instanceof Collection) {
            foreach ($conditions as $condition) {
                $collection->put($condition->getName(), $condition);
            }
        }

        return $collection;
    }
}
