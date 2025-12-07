<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands;

/**
 * Command to remove an item from cart.
 */
final readonly class RemoveItemCommand
{
    public function __construct(
        public string $identifier,
        public string $instance,
        public string $itemId
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
            itemId: $data['item_id'],
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
            'item_id' => $this->itemId,
        ];
    }
}
