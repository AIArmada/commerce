<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use AIArmada\Cart\Models\CartItem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonException;
use JsonSerializable;

final class CartCondition implements Arrayable, Jsonable, JsonSerializable
{
    private ConditionTarget $target;

    private ?PercentageRate $percentageRate = null;

    private string | int $value;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $rules
     * @param  ConditionTarget|string|array<string, mixed>  $target
     */
    public function __construct(
        private string $name,
        private string $type,
        ConditionTarget | string | array $target,
        string | int | float $value,
        private array $attributes = [],
        private int $order = 0,
        private ?array $rules = null,
        private ?self $staticConditionCache = null
    ) {
        $this->target = ConditionTarget::from($target);
        $this->value = $this->normalizeValue($value);
        $this->validateCondition();
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return sprintf(
            '%s (%s): %s',
            $this->name,
            $this->type,
            $this->value
        );
    }

    /**
     * Create condition from array
     *
     * @param  array<string, mixed>  $data
     *                                      Requires `target_definition` (structured array).
     */
    public static function fromArray(array $data): static
    {
        $targetData = $data['target_definition'] ?? null;

        if ($targetData === null) {
            throw new InvalidCartConditionException('Condition target_definition is required.');
        }

        return new self(
            name: $data['name'] ?? throw new InvalidCartConditionException('Condition name is required'),
            type: $data['type'] ?? throw new InvalidCartConditionException('Condition type is required'),
            target: $targetData,
            value: $data['value'] ?? throw new InvalidCartConditionException('Condition value is required'),
            attributes: $data['attributes'] ?? [],
            order: $data['order'] ?? 0,
            rules: $data['rules'] ?? null
        );
    }

    /**
     * Get condition name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get condition type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get condition target
     */
    public function getTargetDefinition(): ConditionTarget
    {
        return $this->target;
    }

    /**
     * Get condition value
     */
    public function getValue(): string | int
    {
        return $this->value;
    }

    /**
     * Get condition attributes
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get specific attribute
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if attribute exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get condition order
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Apply condition to a value using integer arithmetic.
     *
     * @param  int  $amountCents  The amount in cents
     * @return int The result in cents
     */
    public function apply(int $amountCents): int
    {
        $roundingMode = $this->getRoundingMode();

        $result = match ($this->getOperator()) {
            '+' => $amountCents + $this->parseFixedValue(),
            '-' => $amountCents - $this->parseFixedValue(),
            '*' => $this->applyMultiplier($amountCents, $roundingMode),
            '/' => $this->applyDivision($amountCents, $roundingMode),
            '%' => $this->applyPercentage($amountCents, $roundingMode),
            default => $amountCents,
        };

        return max(0, $result);
    }

    /**
     * Get calculated value for display (adjustment amount).
     */
    public function getCalculatedValue(int $baseValue): int
    {
        return $this->apply($baseValue) - $baseValue;
    }

    /**
     * Check if condition is a discount
     */
    public function isDiscount(): bool
    {
        $operator = $this->getOperator();

        if ($operator === '-') {
            return true;
        }

        if ($operator === '%') {
            return $this->getPercentageRate()->isDiscount();
        }

        return false;
    }

    /**
     * Check if condition is a charge/fee
     */
    public function isCharge(): bool
    {
        $operator = $this->getOperator();

        if ($operator === '+') {
            return true;
        }

        if ($operator === '%') {
            return $this->getPercentageRate()->isCharge();
        }

        return false;
    }

    /**
     * Check if condition is percentage-based
     */
    public function isPercentage(): bool
    {
        return $this->getOperator() === '%';
    }

    /**
     * Create a modified copy of the condition
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): static
    {
        $targetInput = $changes['target_definition']
            ?? $this->target;

        return new static(
            name: $changes['name'] ?? $this->name,
            type: $changes['type'] ?? $this->type,
            target: $targetInput,
            value: $changes['value'] ?? $this->value,
            attributes: $changes['attributes'] ?? $this->attributes,
            order: $changes['order'] ?? $this->order,
            rules: $changes['rules'] ?? $this->rules
        );
    }

    /**
     * Check if this is a dynamic condition
     */
    public function isDynamic(): bool
    {
        return $this->rules !== null && ! empty($this->rules);
    }

    /**
     * Get the rules for this condition
     *
     * @return ?array<callable>
     */
    public function getRules(): ?array
    {
        return $this->rules;
    }

    /**
     * Evaluate if the condition should apply based on its rules
     */
    public function shouldApply(Cart $cart, ?CartItem $item = null): bool
    {
        if (! $this->isDynamic()) {
            return true;
        }

        if ($this->rules === null) {
            return true;
        }

        foreach ($this->rules as $rule) {
            if (! $rule($cart, $item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a copy of this condition without rules (for static application)
     */
    public function withoutRules(): static
    {
        if (! $this->isDynamic()) {
            return new static(
                name: $this->name,
                type: $this->type,
                target: $this->target,
                value: $this->value,
                attributes: $this->attributes,
                order: $this->order,
                rules: null
            );
        }

        if ($this->staticConditionCache instanceof self) {
            return $this->staticConditionCache;
        }

        $this->staticConditionCache = new static(
            name: $this->name,
            type: $this->type,
            target: $this->target,
            value: $this->value,
            attributes: $this->attributes,
            order: $this->order,
            rules: null
        );

        return $this->staticConditionCache;
    }

    /**
     * Get the PercentageRate for this condition (cached).
     */
    public function getPercentageRate(): PercentageRate
    {
        if ($this->percentageRate === null) {
            if (! $this->isPercentage()) {
                throw new InvalidCartConditionException('Cannot get percentage rate for non-percentage condition');
            }
            $this->percentageRate = PercentageRate::fromPercentString((string) $this->value);
        }

        return $this->percentageRate;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'target_definition' => $this->target->toArray(),
            'value' => $this->value,
            'attributes' => $this->attributes,
            'order' => $this->order,
            'rules' => $this->rules,
            'operator' => $this->getOperator(),
            'parsed_value' => $this->isPercentage() ? $this->getPercentageRate()->basisPoints : $this->parseFixedValue(),
            'is_discount' => $this->isDiscount(),
            'is_charge' => $this->isCharge(),
            'is_percentage' => $this->isPercentage(),
            'is_dynamic' => $this->isDynamic(),
        ];
    }

    /**
     * Convert to JSON
     *
     * @param  int  $options  JSON encode options
     */
    public function toJson($options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if ($json === false) {
            throw new JsonException('Failed to encode condition to JSON');
        }

        return $json;
    }

    /**
     * JSON serialize
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Normalize value to string or int.
     *
     * Float values (non-percentage) are converted to int cents.
     * String values (percentages or with operators) are kept as strings.
     */
    private function normalizeValue(string | int | float $value): string | int
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            // Float fixed values: convert to int cents
            return (int) round($value * 100);
        }

        return $value;
    }

    /**
     * Validate condition data
     */
    private function validateCondition(): void
    {
        if (empty(mb_trim($this->name))) {
            throw new InvalidCartConditionException('Condition name cannot be empty');
        }

        if (empty(mb_trim($this->type))) {
            throw new InvalidCartConditionException('Condition type cannot be empty');
        }

        if ($this->value === '') {
            throw new InvalidCartConditionException('Condition value cannot be empty');
        }

        if ($this->isPercentage()) {
            $this->getPercentageRate();
        } else {
            $this->parseFixedValue();
        }
    }

    /**
     * Get the operator from value
     */
    private function getOperator(): string
    {
        $value = (string) $this->value;

        // Check for percentage at end (e.g., "10%", "+10%") or start (e.g., "%10")
        if (str_ends_with($value, '%') || str_starts_with($value, '%')) {
            return '%';
        }

        return match ($value[0] ?? '') {
            '+' => '+',
            '-' => '-',
            '*' => '*',
            '/' => '/',
            default => '+',
        };
    }

    /**
     * Parse the fixed value (non-percentage) in cents.
     *
     * String values with decimals (like '15.50') are treated as dollar amounts
     * and converted to cents. Integer string values (like '1500') are treated
     * as already being in cents.
     */
    private function parseFixedValue(): int
    {
        $value = (string) $this->value;

        if (in_array($value[0] ?? '', ['+', '-', '*', '/'])) {
            $numericPart = mb_substr($value, 1);
        } else {
            $numericPart = $value;
        }

        if (! is_numeric($numericPart)) {
            throw new InvalidCartConditionException("Invalid condition value: {$this->value}");
        }

        // Check for invalid special values (string forms)
        if (in_array(mb_strtoupper(mb_trim($value)), ['INF', '-INF', 'INFINITY', '-INFINITY', 'NAN'], true)) {
            throw new InvalidCartConditionException("Invalid condition value: {$this->value}");
        }

        // Check that the numeric value is finite (catches 1e309 etc.)
        $floatValue = (float) $numericPart;
        if (! is_finite($floatValue)) {
            throw new InvalidCartConditionException("Invalid condition value: {$this->value}");
        }

        // If the value contains a decimal point, treat it as dollars and convert to cents
        if (str_contains($numericPart, '.')) {
            return (int) round($floatValue * 100);
        }

        return (int) $numericPart;
    }

    /**
     * Parse a multiplier/divisor value (not money, just a numeric factor).
     */
    private function parseMultiplier(): float
    {
        $value = (string) $this->value;

        if (in_array($value[0] ?? '', ['*', '/'])) {
            $numericPart = mb_substr($value, 1);
        } else {
            $numericPart = $value;
        }

        if (! is_numeric($numericPart)) {
            throw new InvalidCartConditionException("Invalid multiplier value: {$this->value}");
        }

        return abs((float) $numericPart);
    }

    /**
     * Get the rounding mode from config.
     */
    private function getRoundingMode(): string
    {
        if (function_exists('config')) {
            return config('cart.money.rounding_mode', 'half_up');
        }

        return 'half_up';
    }

    /**
     * Apply percentage using integer arithmetic via PercentageRate.
     */
    private function applyPercentage(int $amountCents, string $roundingMode): int
    {
        return $this->getPercentageRate()->apply($amountCents, $roundingMode);
    }

    /**
     * Apply multiplier with rounding.
     */
    private function applyMultiplier(int $amountCents, string $roundingMode): int
    {
        $multiplier = $this->parseMultiplier();
        $result = $amountCents * $multiplier;

        return match ($roundingMode) {
            'floor' => (int) floor($result),
            'ceil' => (int) ceil($result),
            'half_even' => (int) round($result, 0, PHP_ROUND_HALF_EVEN),
            default => (int) round($result),  // half_up
        };
    }

    /**
     * Apply division with rounding.
     */
    private function applyDivision(int $amountCents, string $roundingMode): int
    {
        $divisor = $this->parseMultiplier();

        if ($divisor === 0.0) {
            return $amountCents;
        }

        $result = $amountCents / $divisor;

        return match ($roundingMode) {
            'floor' => (int) floor($result),
            'ceil' => (int) ceil($result),
            'half_even' => (int) round($result, 0, PHP_ROUND_HALF_EVEN),
            default => (int) round($result, 0, PHP_ROUND_HALF_UP),
        };
    }
}
