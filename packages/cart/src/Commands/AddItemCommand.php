<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands;

/**
 * Command to add an item to a cart.
 *
 * Represents the intent to add a product/item to a shopping cart.
 * Immutable value object containing all data needed for the operation.
 */
final readonly class AddItemCommand
{
    /**
     * @param  array<string, mixed>  $attributes  Additional item attributes
     */
    public function __construct(
        public string $identifier,
        public string $instance,
        public string $itemId,
        public string $itemName,
        public int $priceInCents,
        public int $quantity = 1,
        public array $attributes = [],
        public ?string $associatedModel = null,
        public ?string $associatedModelId = null
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
            itemName: $data['item_name'],
            priceInCents: (int) $data['price_in_cents'],
            quantity: (int) ($data['quantity'] ?? 1),
            attributes: $data['attributes'] ?? [],
            associatedModel: $data['associated_model'] ?? null,
            associatedModelId: $data['associated_model_id'] ?? null,
        );
    }

    /**
     * Convert command to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'instance' => $this->instance,
            'item_id' => $this->itemId,
            'item_name' => $this->itemName,
            'price_in_cents' => $this->priceInCents,
            'quantity' => $this->quantity,
            'attributes' => $this->attributes,
            'associated_model' => $this->associatedModel,
            'associated_model_id' => $this->associatedModelId,
        ];
    }
}
