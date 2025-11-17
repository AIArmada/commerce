<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use Illuminate\Support\Collection;

trait ConditionTrait
{
    /**
     * Add condition to item
     */
    public function addCondition(CartCondition $condition): static
    {
        $conditions = $this->conditions->toArray();
        $conditions[$condition->getName()] = $condition;

        return new static(
            $this->id,
            $this->name,
            $this->price,
            $this->quantity,
            $this->attributes->toArray(),
            $conditions,
            $this->associatedModel
        );
    }

    /**
     * Remove condition from item
     */
    public function removeCondition(string $name): static
    {
        $conditions = $this->conditions->toArray();
        unset($conditions[$name]);

        return new static(
            $this->id,
            $this->name,
            $this->price,
            $this->quantity,
            $this->attributes->toArray(),
            $conditions,
            $this->associatedModel
        );
    }

    /**
     * Clear all conditions
     */
    public function clearConditions(): static
    {
        return new static(
            $this->id,
            $this->name,
            $this->price,
            $this->quantity,
            $this->attributes->toArray(),
            [],
            $this->associatedModel
        );
    }

    /**
     * Check if item has specific condition
     */
    public function hasCondition(string $name): bool
    {
        return $this->conditions->has($name);
    }

    /**
     * Check if item has any conditions
     */
    public function hasConditions(): bool
    {
        return $this->conditions->isNotEmpty();
    }

    /**
     * Get condition by name
     */
    public function getCondition(string $name): ?CartCondition
    {
        return $this->conditions->get($name);
    }

    /**
     * Get all conditions
     */
    public function getConditions(): CartConditionCollection
    {
        return $this->conditions;
    }

    /**
     * Normalize conditions to CartConditionCollection
     */
    /**
     * @param  array<string, CartCondition|array<string, mixed>>|Collection  $conditions
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
                if ($condition instanceof CartCondition) {
                    $collection->put($condition->getName(), $condition);
                }
            }
        }

        return $collection;
    }
}
