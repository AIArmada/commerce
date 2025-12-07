<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands;

/**
 * Command to apply a condition to cart.
 */
final readonly class ApplyConditionCommand
{
    /**
     * @param  array<string, mixed>  $attributes  Additional condition attributes
     */
    public function __construct(
        public string $identifier,
        public string $instance,
        public string $conditionName,
        public string $conditionType,
        public string $value,
        public string $target = 'cart@cart_subtotal/aggregate',
        public int $order = 0,
        public array $attributes = []
    ) {}

    /**
     * Create command from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            identifier: $data['identifier'],
            instance: $data['instance'] ?? 'default',
            conditionName: $data['condition_name'],
            conditionType: $data['condition_type'],
            value: $data['value'],
            target: $data['target'] ?? 'cart@cart_subtotal/aggregate',
            order: (int) ($data['order'] ?? 0),
            attributes: $data['attributes'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'instance' => $this->instance,
            'condition_name' => $this->conditionName,
            'condition_type' => $this->conditionType,
            'value' => $this->value,
            'target' => $this->target,
            'order' => $this->order,
            'attributes' => $this->attributes,
        ];
    }
}
