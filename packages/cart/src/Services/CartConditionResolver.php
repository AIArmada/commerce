<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use Closure;

final class CartConditionResolver
{
    /**
     * @var array<int, array{priority: int, resolver: Closure}>
     */
    private array $resolvers = [];

    /**
     * Register a resolver callback.
     *
     * @param  callable(mixed):(?CartCondition)  $resolver
     */
    public function register(callable $resolver, int $priority = 0): void
    {
        $this->resolvers[] = [
            'priority' => $priority,
            'resolver' => Closure::fromCallable($resolver),
        ];

        usort($this->resolvers, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
    }

    public function resolve(mixed $condition): ?CartCondition
    {
        if ($condition instanceof CartCondition) {
            return $condition;
        }

        if ($condition instanceof CartConditionConvertible) {
            return $condition->toCartCondition();
        }

        foreach ($this->resolvers as $entry) {
            $resolved = ($entry['resolver'])($condition);

            if ($resolved instanceof CartCondition) {
                return $resolved;
            }
        }

        return null;
    }

    public function clear(): void
    {
        $this->resolvers = [];
    }
}
