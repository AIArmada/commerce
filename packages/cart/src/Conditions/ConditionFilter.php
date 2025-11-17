<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

final class ConditionFilter implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $field,
        public readonly ConditionFilterOperator $operator,
        public readonly mixed $value
    ) {
        if (mb_trim($field) === '') {
            throw new InvalidArgumentException('Filter field cannot be empty.');
        }

        if ($this->operator->requiresArrayValue() && ! is_array($value)) {
            throw new InvalidArgumentException('Operator '.$this->operator->value.' expects an array value.');
        }
    }

    /**
     * @param  array{field:string, operator:string, value:mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['field'] ?? throw new InvalidArgumentException('Filter field is required.'),
            isset($data['operator'])
                ? ConditionFilterOperator::fromString((string) $data['operator'])
                : throw new InvalidArgumentException('Filter operator is required.'),
            $data['value'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toDslToken(): string
    {
        $value = $this->formatValueForDsl($this->value);

        return "{$this->field}{$this->operator->toDslToken()}{$value}";
    }

    private function formatValueForDsl(mixed $value): string
    {
        if (is_array($value)) {
            $items = array_map(fn ($item) => $this->formatScalarValue($item), $value);

            return '['.implode(',', $items).']';
        }

        return $this->formatScalarValue($value);
    }

    private function formatScalarValue(mixed $value): string
    {
        if (is_string($value)) {
            if ($value === '') {
                return "''";
            }

            if (preg_match('/^[A-Za-z0-9_\-.]+$/', $value) === 1) {
                return $value;
            }

            return '"'.addcslashes($value, '"\\').'"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
