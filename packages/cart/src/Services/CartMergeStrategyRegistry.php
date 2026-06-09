<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Contracts\CartMergeStrategyInterface;
use AIArmada\Cart\Enums\CartMergeStrategy;
use InvalidArgumentException;

final class CartMergeStrategyRegistry
{
    /** @var array<string, CartMergeStrategyInterface> */
    private array $handlers = [];

    public function register(CartMergeStrategyInterface $handler, ?string $name = null): void
    {
        $key = $name ?? $handler::class;
        $this->handlers[$key] = $handler;
    }

    public function registerBuiltIns(): void
    {
        $this->register(new class implements CartMergeStrategyInterface
        {
            public function resolveConflict(int $userQuantity, int $guestQuantity): int
            {
                return $userQuantity + $guestQuantity;
            }
        }, CartMergeStrategy::ADD_QUANTITIES->value);

        $this->register(new class implements CartMergeStrategyInterface
        {
            public function resolveConflict(int $userQuantity, int $guestQuantity): int
            {
                return max($userQuantity, $guestQuantity);
            }
        }, CartMergeStrategy::KEEP_HIGHEST_QUANTITY->value);

        $this->register(new class implements CartMergeStrategyInterface
        {
            public function resolveConflict(int $userQuantity, int $guestQuantity): int
            {
                return $userQuantity;
            }
        }, CartMergeStrategy::KEEP_USER_CART->value);

        $this->register(new class implements CartMergeStrategyInterface
        {
            public function resolveConflict(int $userQuantity, int $guestQuantity): int
            {
                return $guestQuantity;
            }
        }, CartMergeStrategy::REPLACE_WITH_GUEST->value);
    }

    public function get(string $name): CartMergeStrategyInterface
    {
        if (! isset($this->handlers[$name])) {
            throw new InvalidArgumentException("Unknown cart merge strategy: {$name}");
        }

        return $this->handlers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[$name]);
    }

    public function resolveFromConfig(): CartMergeStrategyInterface
    {
        $strategy = config('cart.migration.merge_strategy', 'add_quantities');

        if ($this->has($strategy)) {
            return $this->get($strategy);
        }

        return $this->get(CartMergeStrategy::ADD_QUANTITIES->value);
    }
}
