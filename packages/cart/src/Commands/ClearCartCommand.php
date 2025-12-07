<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands;

/**
 * Command to clear all items from cart.
 */
final readonly class ClearCartCommand
{
    public function __construct(
        public string $identifier,
        public string $instance = 'default'
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
        ];
    }
}
