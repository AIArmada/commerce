<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Support\Collection;

final readonly class PromotionTargetingCart
{
    /**
     * @param  Collection<int, CartItem>  $items
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private int $subtotal,
        private Collection $items,
        private array $metadata,
    ) {}

    public static function fromSession(CheckoutSession $session): self
    {
        $snapshot = $session->cart_snapshot ?? [];
        $items = collect();

        foreach (is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (! is_string($id) || $id === '' || ! is_int($quantity) || $quantity < 1) {
                continue;
            }

            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];

            $items->push(new CartItem(
                id: $id,
                name: is_string($item['name'] ?? null) && $item['name'] !== '' ? $item['name'] : $id,
                price: max(0, (int) ($item['price'] ?? 0)),
                quantity: $quantity,
                attributes: $attributes,
            ));
        }

        return new self(
            subtotal: max(0, (int) $session->subtotal),
            items: $items,
            metadata: is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [],
        );
    }

    public function getRawSubtotalWithoutConditions(): int
    {
        return $this->subtotal;
    }

    /** @return Collection<int, CartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @return array<string, mixed> */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    public function getMetadata(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
}
