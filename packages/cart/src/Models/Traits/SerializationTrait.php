<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

trait SerializationTrait
{
    /**
     * Convert to string representation
     */
    public function __toString(): string
    {
        return sprintf(
            '%s (ID: %s, Price: %.2f, Quantity: %d)',
            $this->name,
            $this->id,
            $this->price,
            $this->quantity
        );
    }

    /**
     * Convert to array
     *
     * Note: Subtotal is intentionally NOT included here because it's a calculated value
     * that should be computed on-the-fly, not stored in the database.
     * Use getSubtotal() method to get the calculated subtotal when needed.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'attributes' => $this->attributes->toArray(),
            'conditions' => $this->conditions->toArray(),
            'associated_model' => $this->getAssociatedModelArray(),
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson($options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        return $json !== false ? $json : '{}';
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
}
