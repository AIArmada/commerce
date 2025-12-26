<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\BuyableInterface;
use AIArmada\Cart\Models\CartItem;
use Akaunting\Money\Money;
use Closure;

/**
 * Pre-checkout cart validator.
 *
 * Validates cart items for checkout readiness including stock,
 * quantity limits, price changes, and custom business rules.
 * All amounts are in cents (the smallest currency unit), matching cart's internal representation.
 *
 * @example
 * ```php
 * $validator = CartValidator::create()
 *     ->requireMinimumTotal(5000)  // $50.00 minimum (5000 cents)
 *     ->requireNonEmpty()
 *     ->addRule('stock', fn($item) => $item->buyable->inStock($item->quantity))
 *     ->addRule('price_check', fn($item) => $item->price === $item->buyable->getPrice());
 *
 * $result = $validator->validate($cart);
 * if ($result->hasFailed()) {
 *     foreach ($result->getErrors() as $error) {
 *         echo $error->getMessage();
 *     }
 * }
 * ```
 */
final class CartValidator
{
    /** @var array<string, Closure(CartItem): ValidationError|null> */
    private array $itemRules = [];

    /** @var array<string, Closure(Cart): ValidationError|null> */
    private array $cartRules = [];

    private bool $stopOnFirstError = false;

    private function __construct() {}

    /**
     * Create a new cart validator instance.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Create with common checkout validation rules.
     */
    public static function forCheckout(): self
    {
        return self::create()
            ->requireNonEmpty()
            ->validateQuantityLimits()
            ->validatePrices()
            ->validateAvailability();
    }

    /**
     * Stop validation on first error encountered.
     */
    public function stopOnFirstError(bool $stop = true): self
    {
        $this->stopOnFirstError = $stop;

        return $this;
    }

    /**
     * Add an item-level validation rule.
     *
     * @param  string  $name  Rule identifier
     * @param  Closure(CartItem): (ValidationError|bool|string|null)  $rule  Returns null/true if valid, error otherwise
     */
    public function addRule(string $name, Closure $rule): self
    {
        $this->itemRules[$name] = $rule;

        return $this;
    }

    /**
     * Add a cart-level validation rule.
     *
     * @param  string  $name  Rule identifier
     * @param  Closure(Cart): (ValidationError|bool|string|null)  $rule  Returns null/true if valid, error otherwise
     */
    public function addCartRule(string $name, Closure $rule): self
    {
        $this->cartRules[$name] = $rule;

        return $this;
    }

    /**
     * Require cart to have items.
     */
    public function requireNonEmpty(): self
    {
        $this->cartRules['non_empty'] = fn (Cart $cart): ?ValidationError => $cart->isEmpty()
            ? ValidationError::cart('non_empty', 'Cart is empty')
            : null;

        return $this;
    }

    /**
     * Require minimum cart total (in cents).
     *
     * @param  int  $minimum  Minimum total in cents (e.g., 5000 for $50.00)
     */
    public function requireMinimumTotal(int $minimum, ?string $message = null): self
    {
        $this->cartRules['minimum_total'] = function (Cart $cart) use ($minimum, $message): ?ValidationError {
            $total = (int) $cart->getRawTotal();
            if ($total < $minimum) {
                $currency = config('cart.money.default_currency', 'USD');
                $formattedMin = (string) Money::{$currency}($minimum);
                $formattedCurrent = (string) Money::{$currency}($total);

                return ValidationError::cart(
                    'minimum_total',
                    $message ?? "Minimum order total is {$formattedMin}. Current: {$formattedCurrent}"
                );
            }

            return null;
        };

        return $this;
    }

    /**
     * Require maximum cart total (in cents).
     *
     * @param  int  $maximum  Maximum total in cents (e.g., 100000 for $1000.00)
     */
    public function requireMaximumTotal(int $maximum, ?string $message = null): self
    {
        $this->cartRules['maximum_total'] = function (Cart $cart) use ($maximum, $message): ?ValidationError {
            $total = (int) $cart->getRawTotal();
            if ($total > $maximum) {
                $currency = config('cart.money.default_currency', 'USD');
                $formattedMax = (string) Money::{$currency}($maximum);
                $formattedCurrent = (string) Money::{$currency}($total);

                return ValidationError::cart(
                    'maximum_total',
                    $message ?? "Maximum order total is {$formattedMax}. Current: {$formattedCurrent}"
                );
            }

            return null;
        };

        return $this;
    }

    /**
     * Require maximum item count (total quantity).
     */
    public function requireMaximumItems(int $maximum, ?string $message = null): self
    {
        $this->cartRules['maximum_items'] = function (Cart $cart) use ($maximum, $message): ?ValidationError {
            $count = $cart->getTotalQuantity();
            if ($count > $maximum) {
                return ValidationError::cart(
                    'maximum_items',
                    $message ?? "Maximum {$maximum} items allowed. Current: {$count}"
                );
            }

            return null;
        };

        return $this;
    }

    /**
     * Validate item quantities against buyable limits.
     */
    public function validateQuantityLimits(): self
    {
        $this->itemRules['quantity_limits'] = function (CartItem $item): ?ValidationError {
            $buyable = $item->getAssociatedModel();

            if (! $buyable instanceof BuyableInterface) {
                return null;
            }

            $qty = $item->quantity;

            // Check minimum
            $min = $buyable->getMinimumQuantity();
            if ($qty < $min) {
                return ValidationError::item(
                    $item->id,
                    'quantity_min',
                    "Minimum quantity is {$min}"
                );
            }

            // Check maximum
            $max = $buyable->getMaximumQuantity();
            if ($max !== null && $qty > $max) {
                return ValidationError::item(
                    $item->id,
                    'quantity_max',
                    "Maximum quantity is {$max}"
                );
            }

            return null;
        };

        return $this;
    }

    /**
     * Validate item prices haven't changed.
     */
    public function validatePrices(): self
    {
        $this->itemRules['price_check'] = function (CartItem $item): ?ValidationError {
            $buyable = $item->getAssociatedModel();

            if (! $buyable instanceof BuyableInterface) {
                return null;
            }

            $currentPrice = (int) $buyable->getBuyablePrice()->getAmount();
            if ($item->price !== $currentPrice) {
                return ValidationError::item(
                    $item->id,
                    'price_changed',
                    "Price has changed from {$item->price} to {$currentPrice}"
                );
            }

            return null;
        };

        return $this;
    }

    /**
     * Validate buyable is still available.
     */
    public function validateAvailability(): self
    {
        $this->itemRules['availability'] = function (CartItem $item): ?ValidationError {
            $buyable = $item->getAssociatedModel();

            if (! $buyable instanceof BuyableInterface) {
                return null;
            }

            if (! $buyable->canBePurchased()) {
                return ValidationError::item(
                    $item->id,
                    'unavailable',
                    'This item is no longer available'
                );
            }

            return null;
        };

        return $this;
    }

    /**
     * Validate the cart.
     */
    public function validate(Cart $cart): ValidationResult
    {
        $errors = [];

        // Run cart-level rules first
        foreach ($this->cartRules as $rule) {
            $result = $this->normalizeRuleResult($rule($cart));
            if ($result !== null) {
                $errors[] = $result;
                if ($this->stopOnFirstError) {
                    return new ValidationResult($errors);
                }
            }
        }

        // Run item-level rules
        foreach ($cart->getItems() as $item) {
            foreach ($this->itemRules as $rule) {
                $result = $rule($item);
                $error = $this->normalizeRuleResult($result, $item->id);
                if ($error !== null) {
                    $errors[] = $error;
                    if ($this->stopOnFirstError) {
                        return new ValidationResult($errors);
                    }
                }
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * Normalize different rule return types to ValidationError or null.
     */
    private function normalizeRuleResult(mixed $result, ?string $itemId = null): ?ValidationError
    {
        if ($result === null || $result === true) {
            return null;
        }

        if ($result instanceof ValidationError) {
            return $result;
        }

        if ($result === false) {
            return $itemId !== null
                ? ValidationError::item($itemId, 'custom', 'Validation failed')
                : ValidationError::cart('custom', 'Validation failed');
        }

        if (is_string($result)) {
            return $itemId !== null
                ? ValidationError::item($itemId, 'custom', $result)
                : ValidationError::cart('custom', $result);
        }

        return null;
    }
}

/**
 * Validation result container.
 */
final readonly class ValidationResult
{
    /**
     * @param  array<ValidationError>  $errors
     */
    public function __construct(
        private array $errors = []
    ) {}

    /**
     * Check if validation passed.
     */
    public function hasPassed(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed.
     */
    public function hasFailed(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get all validation errors.
     *
     * @return array<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific item.
     *
     * @return array<ValidationError>
     */
    public function getErrorsForItem(string $itemId): array
    {
        return array_filter(
            $this->errors,
            fn (ValidationError $error): bool => $error->itemId === $itemId
        );
    }

    /**
     * Get cart-level errors only.
     *
     * @return array<ValidationError>
     */
    public function getCartErrors(): array
    {
        return array_filter(
            $this->errors,
            fn (ValidationError $error): bool => $error->itemId === null
        );
    }

    /**
     * Get item-level errors only.
     *
     * @return array<ValidationError>
     */
    public function getItemErrors(): array
    {
        return array_filter(
            $this->errors,
            fn (ValidationError $error): bool => $error->itemId !== null
        );
    }

    /**
     * Get error messages as array.
     *
     * @return array<string>
     */
    public function getMessages(): array
    {
        return array_map(
            fn (ValidationError $error): string => $error->message,
            $this->errors
        );
    }

    /**
     * Get first error or null.
     */
    public function getFirstError(): ?ValidationError
    {
        return $this->errors[0] ?? null;
    }
}

/**
 * Validation error representation.
 */
final readonly class ValidationError
{
    public function __construct(
        public ?string $itemId,
        public string $rule,
        public string $message,
        public array $context = []
    ) {}

    /**
     * Create an item-level error.
     */
    public static function item(string $itemId, string $rule, string $message, array $context = []): self
    {
        return new self($itemId, $rule, $message, $context);
    }

    /**
     * Create a cart-level error.
     */
    public static function cart(string $rule, string $message, array $context = []): self
    {
        return new self(null, $rule, $message, $context);
    }

    /**
     * Get the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Check if this is an item-level error.
     */
    public function isItemError(): bool
    {
        return $this->itemId !== null;
    }

    /**
     * Check if this is a cart-level error.
     */
    public function isCartError(): bool
    {
        return $this->itemId === null;
    }
}
