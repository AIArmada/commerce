<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\ItemConditionAdded;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use Traversable;

trait ManagesConditions
{
    /**
     * Add condition to cart
     *
     * @param  mixed  $condition  Condition instance, array definition, or iterable list of conditions
     *
     * @throws InvalidCartConditionException If attempting to add a dynamic condition (with rules) or conversion fails
     */
    public function addCondition(mixed $condition): static
    {
        foreach ($this->normalizeConditionInput($condition) as $entry) {
            $cartCondition = $this->resolveToCartCondition($entry);

            if ($cartCondition->isDynamic()) {
                throw new InvalidCartConditionException(
                    sprintf(
                        'Cannot add dynamic condition "%s" using addCondition(). Dynamic conditions (with validation rules) must be registered using registerDynamicCondition() instead. Alternatively, create a static copy using withoutRules() if you want to bypass validation.',
                        $cartCondition->getName()
                    )
                );
            }

            $this->addCartCondition($cartCondition);
        }

        return $this;
    }

    /**
     * Get cart conditions
     */
    public function getConditions(): CartConditionCollection
    {
        $conditions = $this->storage->getConditions($this->getIdentifier(), $this->instance());

        if (! $conditions || ! is_array($conditions)) { // @phpstan-ignore function.alreadyNarrowedType
            return new CartConditionCollection;
        }

        // Convert array back to CartConditionCollection
        $collection = new CartConditionCollection;
        foreach ($conditions as $conditionData) {
            if (is_array($conditionData) && isset($conditionData['name'])) {
                $condition = CartCondition::fromArray($conditionData);
                $collection->put($condition->getName(), $condition);
            }
        }

        return $collection;
    }

    /**
     * Get condition by name
     */
    public function getCondition(string $name): mixed
    {
        return $this->getConditions()->get($name);
    }

    /**
     * Remove cart condition by name
     */
    public function removeCondition(string $name): bool
    {
        $conditions = $this->getConditions();

        if (! $conditions->has($name)) {
            return false;
        }

        // Get the condition before removing it for the event
        $removedCondition = $conditions->get($name);

        $conditions->forget($name);
        $conditionsArray = $conditions->toArray();
        $this->storage->putConditions($this->getIdentifier(), $this->instance(), $conditionsArray);

        // Dispatch cart-level condition removed event
        if ($this->eventsEnabled && $this->events && $removedCondition) {
            $this->events->dispatch(new CartConditionRemoved($removedCondition, $this));
        }

        return true;
    }

    /**
     * Clear all cart conditions
     */
    public function clearConditions(): bool
    {
        $this->storage->putConditions($this->getIdentifier(), $this->instance(), []);

        return true;
    }

    /**
     * Get conditions by type
     */
    public function getConditionsByType(string $type): CartConditionCollection
    {
        return $this->getConditions()->filter(fn (CartCondition $condition) => $condition->getType() === $type);
    }

    /**
     * Remove conditions by type
     */
    public function removeConditionsByType(string $type): bool
    {
        $conditions = $this->getConditions();
        $conditionsToRemove = $conditions->filter(fn (CartCondition $condition) => $condition->getType() === $type);

        if ($conditionsToRemove->isEmpty()) {
            return false;
        }

        foreach ($conditionsToRemove as $condition) {
            $conditions->forget($condition->getName());
        }

        $conditionsArray = $conditions->toArray();
        $this->storage->putConditions($this->getIdentifier(), $this->instance(), $conditionsArray);

        return true;
    }

    /**
     * Add condition to specific item
     */
    public function addItemCondition(string $itemId, CartCondition $condition): bool
    {
        $cartItems = $this->getItems();

        if (! $cartItems->has($itemId)) {
            return false;
        }

        $item = $cartItems->get($itemId);
        assert($item !== null, 'Item should exist since we checked has()');
        $item = $item->addCondition($condition);
        $cartItems->put($itemId, $item);
        $this->save($cartItems);

        // Dispatch item-level condition added event
        if ($this->eventsEnabled && $this->events) {
            $this->events->dispatch(new ItemConditionAdded($condition, $this, $itemId));
        }

        return true;
    }

    /**
     * Remove condition from specific item
     */
    public function removeItemCondition(string $itemId, string $conditionName): bool
    {
        $cartItems = $this->getItems();

        if (! $cartItems->has($itemId)) {
            return false;
        }

        $item = $cartItems->get($itemId);
        assert($item !== null, 'Item should exist since we checked has()');

        // Check if the condition exists before removing
        if (! $item->conditions->has($conditionName)) {
            return false;
        }

        // Get the condition before removing it for the event
        $removedCondition = $item->conditions->get($conditionName);

        $item = $item->removeCondition($conditionName);
        $cartItems->put($itemId, $item);
        $this->save($cartItems);

        // Dispatch item-level condition removed event
        if ($this->eventsEnabled && $this->events && $removedCondition) {
            $this->events->dispatch(new ItemConditionRemoved($removedCondition, $this, $itemId));
        }

        return true;
    }

    /**
     * Clear all conditions from specific item
     */
    public function clearItemConditions(string $itemId): bool
    {
        $cartItems = $this->getItems();

        if (! $cartItems->has($itemId)) {
            return false;
        }

        $item = $cartItems->get($itemId);
        assert($item !== null, 'Item should exist since we checked has()');
        $item = $item->clearConditions();
        $cartItems->put($itemId, $item);
        $this->save($cartItems);

        return true;
    }

    /**
     * Add a simple discount condition (shopping-cart style)
     */
    public function addDiscount(string $name, string $value, string $target = 'subtotal'): static
    {
        // Ensure discount values are negative
        if (! str_starts_with($value, '-')) {
            $value = '-'.$value;
        }
        $condition = new CartCondition($name, 'discount', $target, $value);
        $this->addCondition($condition);

        return $this;
    }

    /**
     * Add a simple fee condition (shopping-cart style)
     * Fees are applied to the total (after discounts and taxes)
     */
    public function addFee(string $name, string $value, string $target = 'total'): static
    {
        $condition = new CartCondition($name, 'fee', $target, $value);
        $this->addCondition($condition);

        return $this;
    }

    /**
     * Add a simple tax condition (shopping-cart style)
     */
    public function addTax(string $name, string $value, string $target = 'subtotal'): static
    {
        $condition = new CartCondition($name, 'tax', $target, $value);
        $this->addCondition($condition);

        return $this;
    }

    /**
     * Add a shipping condition (shopping-cart style)
     * Shipping is applied to the total (after discounts and taxes)
     *
     * @param  string  $name  The name of the shipping condition
     * @param  string|float  $value  The value of the shipping fee (e.g. '15.00', '+15', etc.)
     * @param  string  $method  The shipping method identifier (e.g. 'standard', 'express')
     * @param  array<string, mixed>  $attributes  Additional attributes to store with the condition
     */
    public function addShipping(string $name, string|float $value, string $method = 'standard', array $attributes = []): static
    {
        // Ensure value is prefixed with + if it's a string and doesn't start with an operator
        if (is_string($value) && ! preg_match('/^[+\-*\/%]/', $value)) {
            $value = '+'.$value;
        }

        // Merge the attributes with the shipping method
        $shippingAttributes = array_merge($attributes, [
            'method' => $method,
            'description' => $name,
        ]);

        // Remove any existing shipping conditions first
        $this->removeShipping();

        // Create and add the condition - shipping is applied to total
        $condition = new CartCondition(
            name: $name,
            type: 'shipping',
            target: 'total',
            value: $value,
            attributes: $shippingAttributes
        );
        $this->addCondition($condition);

        return $this;
    }

    /**
     * Remove all shipping conditions from the cart
     */
    public function removeShipping(): void
    {
        // Get all conditions
        $conditions = $this->getConditions();

        // Find and remove shipping conditions
        foreach ($conditions as $condition) {
            if ($condition->getType() === 'shipping') {
                $this->removeCondition($condition->getName());
            }
        }
    }

    /**
     * Get the current shipping condition if any
     */
    public function getShipping(): ?CartCondition
    {
        // Get all conditions
        $conditions = $this->getConditions();

        // Find the first shipping condition
        foreach ($conditions as $condition) {
            if ($condition->getType() === 'shipping') {
                return $condition;
            }
        }

        return null;
    }

    /**
     * Get the shipping method from the cart condition
     */
    public function getShippingMethod(): ?string
    {
        $shipping = $this->getShipping();

        if ($shipping) {
            $attributes = $shipping->getAttributes();

            return $attributes['method'] ?? null;
        }

        return null;
    }

    /**
     * Get shipping condition value
     */
    public function getShippingValue(): ?float
    {
        $shipping = $this->getShipping();

        if ($shipping) {
            $value = $shipping->getValue();

            // Parse the value to remove operator and convert to float
            if (is_string($value) && preg_match('/^([+\-*\/%])(.+)$/', $value, $matches)) {
                $operator = $matches[1];
                $numericValue = (float) $matches[2];

                return $operator === '-' ? -$numericValue : $numericValue;
            }

            return (float) $value;
        }

        return null;
    }

    /**
     * Add a cart condition
     */
    private function addCartCondition(CartCondition $condition): void
    {
        $conditions = $this->getConditions();
        $conditions->put($condition->getName(), $condition);
        $conditionsArray = $conditions->toArray();
        $this->storage->putConditions($this->getIdentifier(), $this->instance(), $conditionsArray);

        // Dispatch cart-level condition added event
        if ($this->eventsEnabled && $this->events) {
            $this->events->dispatch(new CartConditionAdded($condition, $this));
        }
    }

    /**
     * Normalize the incoming condition payload into a flat list of entries.
     *
     * @return array<int, mixed>
     */
    private function normalizeConditionInput(mixed $condition): array
    {
        if ($condition instanceof CartCondition || $condition instanceof CartConditionConvertible) {
            return [$condition];
        }

        if ($condition instanceof Traversable) {
            return iterator_to_array($condition);
        }

        if (is_array($condition)) {
            return array_is_list($condition) ? $condition : [$condition];
        }

        return [$condition];
    }

    private function resolveToCartCondition(mixed $condition): CartCondition
    {
        if ($condition instanceof CartCondition) {
            return $condition;
        }

        if ($condition instanceof CartConditionConvertible) {
            $resolved = $condition->toCartCondition();
        } elseif (is_array($condition) && ! array_is_list($condition)) {
            $resolved = CartCondition::fromArray($condition);
        } else {
            $resolved = $this->getConditionResolver()->resolve($condition);
        }

        if (! $resolved instanceof CartCondition) {
            $type = is_object($condition) ? $condition::class : gettype($condition);

            throw new InvalidCartConditionException("Condition of type {$type} cannot be converted to CartCondition");
        }

        return $resolved;
    }
}
