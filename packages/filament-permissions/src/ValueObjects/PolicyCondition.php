<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\ValueObjects;

use AIArmada\FilamentPermissions\Enums\ConditionOperator;

final readonly class PolicyCondition
{
    public function __construct(
        public string $attribute,
        public ConditionOperator $operator,
        public mixed $value,
        public ?string $description = null
    ) {}

    /**
     * Create from array.
     *
     * @param  array{attribute: string, operator: string, value: mixed, description?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attribute: $data['attribute'],
            operator: ConditionOperator::from($data['operator']),
            value: $data['value'],
            description: $data['description'] ?? null
        );
    }

    /**
     * Create an equals condition.
     */
    public static function equals(string $attribute, mixed $value): self
    {
        return new self($attribute, ConditionOperator::Equals, $value);
    }

    /**
     * Create a not equals condition.
     */
    public static function notEquals(string $attribute, mixed $value): self
    {
        return new self($attribute, ConditionOperator::NotEquals, $value);
    }

    /**
     * Create a greater than condition.
     */
    public static function greaterThan(string $attribute, mixed $value): self
    {
        return new self($attribute, ConditionOperator::GreaterThan, $value);
    }

    /**
     * Create a less than condition.
     */
    public static function lessThan(string $attribute, mixed $value): self
    {
        return new self($attribute, ConditionOperator::LessThan, $value);
    }

    /**
     * Create an "in" condition.
     *
     * @param  array<mixed>  $values
     */
    public static function in(string $attribute, array $values): self
    {
        return new self($attribute, ConditionOperator::In, $values);
    }

    /**
     * Create a "not in" condition.
     *
     * @param  array<mixed>  $values
     */
    public static function notIn(string $attribute, array $values): self
    {
        return new self($attribute, ConditionOperator::NotIn, $values);
    }

    /**
     * Create a contains condition.
     */
    public static function contains(string $attribute, string $value): self
    {
        return new self($attribute, ConditionOperator::Contains, $value);
    }

    /**
     * Create a "starts with" condition.
     */
    public static function startsWith(string $attribute, string $value): self
    {
        return new self($attribute, ConditionOperator::StartsWith, $value);
    }

    /**
     * Create a "between" condition.
     *
     * @param  array{0: mixed, 1: mixed}  $range
     */
    public static function between(string $attribute, array $range): self
    {
        return new self($attribute, ConditionOperator::Between, $range);
    }

    /**
     * Create a null check condition.
     */
    public static function isNull(string $attribute): self
    {
        return new self($attribute, ConditionOperator::IsNull, null);
    }

    /**
     * Create a not null check condition.
     */
    public static function isNotNull(string $attribute): self
    {
        return new self($attribute, ConditionOperator::IsNotNull, null);
    }

    /**
     * Create a regex match condition.
     */
    public static function matches(string $attribute, string $pattern): self
    {
        return new self($attribute, ConditionOperator::Matches, $pattern);
    }

    /**
     * Convert to array.
     *
     * @return array{attribute: string, operator: string, value: mixed, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'operator' => $this->operator->value,
            'value' => $this->value,
            'description' => $this->description,
        ];
    }

    /**
     * Evaluate the condition against a context.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $context): bool
    {
        // Get the attribute value from context using dot notation
        $actualValue = data_get($context, $this->attribute);

        return $this->operator->evaluate($actualValue, $this->value);
    }

    /**
     * Get human-readable description of the condition.
     */
    public function describe(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        $operatorLabel = $this->operator->label();
        $valueDisplay = is_array($this->value)
            ? '['.implode(', ', array_map(fn ($v) => (string) $v, $this->value)).']'
            : (string) $this->value;

        return "{$this->attribute} {$operatorLabel} {$valueDisplay}";
    }
}
