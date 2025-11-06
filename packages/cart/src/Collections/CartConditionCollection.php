<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collections;

use AIArmada\Cart\Conditions\CartCondition;
use Akaunting\Money\Money;
use Illuminate\Support\Collection;

final class CartConditionCollection extends Collection
{
    /**
     * Create conditions from array
     *
     * @param  array<string, mixed>  $conditions
     */
    public static function fromArray(array $conditions): static
    {
        $collection = new self;

        foreach ($conditions as $condition) {
            $collection->addCondition(CartCondition::fromArray($condition));
        }

        return $collection;
    }

    /**
     * Add a condition to the collection
     */
    public function addCondition(CartCondition $condition): static
    {
        return $this->put($condition->getName(), $condition);
    }

    /**
     * Remove a condition from the collection
     */
    public function removeCondition(string $name): static
    {
        return $this->forget($name);
    }

    /**
     * Get condition by name
     */
    public function getCondition(string $name): ?CartCondition
    {
        return $this->get($name);
    }

    /**
     * Check if condition exists
     */
    public function hasCondition(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Filter conditions by type
     */
    public function byType(string $type): static
    {
        return $this->filter(fn (CartCondition $condition) => $condition->getType() === $type);
    }

    /**
     * Filter conditions by target
     */
    public function byTarget(string $target): static
    {
        return $this->filter(fn (CartCondition $condition) => $condition->getTarget() === $target);
    }

    /**
     * Filter conditions by value
     */
    public function byValue(string|float $value): static
    {
        return $this->filter(fn (CartCondition $condition) => $condition->getValue() === $value);
    }

    /**
     * Get only discount conditions
     */
    public function discounts(): static
    {
        return $this->filter(fn (CartCondition $condition) => $condition->isDiscount());
    }

    /**
     * Get only charge/fee conditions
     */
    public function charges(): static
    {
        return $this->filter(fn (CartCondition $condition) => $condition->isCharge());
    }

    /**
     * Get only percentage-based conditions
     */
    public function percentages(): static
    {
        return $this->filter(fn (CartCondition $condition) => $condition->isPercentage());
    }

    /**
     * Sort conditions by order
     */
    public function sortByOrder(): static
    {
        return $this->sortBy(fn (CartCondition $condition) => $condition->getOrder());
    }

    /**
     * Apply all conditions to a value
     */
    public function applyAll(float $value): Money
    {
        $result = $this->sortByOrder()->reduce(
            fn (float $carry, CartCondition $condition) => $condition->apply($carry),
            $value
        );

        // Return as Laravel Money object (default currency from config)
        $currency = config('cart.money.default_currency', 'MYR');

        return Money::{$currency}($result);
    }

    /**
     * Get total discount amount
     */
    public function getTotalDiscount(float $baseValue): float
    {
        return $this->discounts()->sum(fn (CartCondition $condition) => abs($condition->getCalculatedValue($baseValue)));
    }

    /**
     * Get total charges amount
     */
    public function getTotalCharges(float $baseValue): float
    {
        return $this->charges()->sum(fn (CartCondition $condition) => $condition->getCalculatedValue($baseValue));
    }

    /**
     * Get conditions summary
     *
     * @return array<string, mixed>
     */
    public function getSummary(float $baseValue = 0): array
    {
        return [
            'total_conditions' => $this->count(),
            'discounts' => $this->discounts()->count(),
            'charges' => $this->charges()->count(),
            'percentages' => $this->percentages()->count(),
            'total_discount_amount' => $baseValue > 0 ? $this->getTotalDiscount($baseValue) : 0,
            'total_charges_amount' => $baseValue > 0 ? $this->getTotalCharges($baseValue) : 0,
            'net_adjustment' => $baseValue > 0 ? (float) $this->applyAll($baseValue)->getValue() - $baseValue : 0,
        ];
    }

    /**
     * Convert collection to array with detailed information
     *
     * @return array<string, mixed>
     */
    public function toDetailedArray(float $baseValue = 0): array
    {
        return [
            'conditions' => $this->map(fn (CartCondition $condition) => [
                ...$condition->toArray(),
                'calculated_value' => $baseValue > 0 ? $condition->getCalculatedValue($baseValue) : 0,
            ])->toArray(),
            'summary' => $this->getSummary($baseValue),
        ];
    }

    /**
     * Group conditions by type
     *
     * @return Collection<string, static>
     */
    public function groupByType(): Collection
    {
        return $this->groupBy(fn (CartCondition $condition) => $condition->getType());
    }

    /**
     * Group conditions by target
     *
     * @return Collection<string, static>
     */
    public function groupByTarget(): Collection
    {
        return $this->groupBy(fn (CartCondition $condition) => $condition->getTarget());
    }

    /**
     * Check if collection has any discount conditions
     */
    public function hasDiscounts(): bool
    {
        return $this->contains(fn (CartCondition $condition) => $condition->isDiscount());
    }

    /**
     * Check if collection has any charge conditions
     */
    public function hasCharges(): bool
    {
        return $this->contains(fn (CartCondition $condition) => $condition->isCharge());
    }

    /**
     * Get conditions with specific attribute
     */
    public function withAttribute(string $key, mixed $value = null): static
    {
        return $this->filter(function (CartCondition $condition) use ($key, $value) {
            if ($value === null) {
                return $condition->hasAttribute($key);
            }

            return $condition->getAttribute($key) === $value;
        });
    }

    /**
     * Find condition by attribute
     */
    public function findByAttribute(string $key, mixed $value): ?CartCondition
    {
        return $this->first(fn (CartCondition $condition) => $condition->getAttribute($key) === $value);
    }

    /**
     * Remove conditions by type
     */
    public function removeByType(string $type): static
    {
        return $this->reject(fn (CartCondition $condition) => $condition->getType() === $type);
    }

    /**
     * Remove conditions by target
     */
    public function removeByTarget(string $target): static
    {
        return $this->reject(fn (CartCondition $condition) => $condition->getTarget() === $target);
    }
}
