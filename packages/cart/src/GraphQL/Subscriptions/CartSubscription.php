<?php

declare(strict_types=1);

namespace AIArmada\Cart\GraphQL\Subscriptions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;

/**
 * GraphQL Subscription definitions for Cart.
 *
 * Provides subscription definitions and transformers that can be used with
 * Lighthouse or other GraphQL subscription implementations (websockets, etc).
 */
final class CartSubscription
{
    /**
     * Events that trigger cart update subscriptions.
     *
     * @var array<class-string>
     */
    public const CART_UPDATED_EVENTS = [
        ItemAdded::class,
        ItemUpdated::class,
        ItemRemoved::class,
        CartCleared::class,
        CartConditionAdded::class,
        CartConditionRemoved::class,
    ];

    /**
     * Events that trigger cart item change subscriptions.
     *
     * @var array<class-string>
     */
    public const CART_ITEM_EVENTS = [
        ItemAdded::class,
        ItemUpdated::class,
        ItemRemoved::class,
    ];

    /**
     * Get the subscription SDL definitions.
     */
    public static function sdl(): string
    {
        return <<<'GRAPHQL'
extend type Subscription {
    "Subscribe to all cart updates for a specific cart"
    cartUpdated(identifier: String!, instance: String = "default"): CartUpdatePayload!
    
    "Subscribe to cart item changes for a specific cart"
    cartItemChanged(identifier: String!, instance: String = "default"): CartItemChangePayload!
    
    "Subscribe to cart condition changes"
    cartConditionChanged(identifier: String!, instance: String = "default"): CartConditionChangePayload!
    
    "Subscribe to checkout status updates"
    checkoutStatusUpdated(checkoutId: ID!): CheckoutStatusPayload!
}

type CartUpdatePayload {
    event: CartEventType!
    cart: Cart!
    timestamp: String!
    metadata: JSON
}

type CartItemChangePayload {
    event: CartItemEventType!
    item: CartItem!
    cart: Cart!
    timestamp: String!
}

type CartConditionChangePayload {
    event: CartConditionEventType!
    condition: CartCondition
    cart: Cart!
    timestamp: String!
}

type CheckoutStatusPayload {
    checkoutId: ID!
    status: CheckoutStatus!
    stage: String
    message: String
    orderId: ID
    paymentUrl: String
    timestamp: String!
}

enum CartEventType {
    ITEM_ADDED
    ITEM_UPDATED
    ITEM_REMOVED
    CART_CLEARED
    CONDITION_ADDED
    CONDITION_REMOVED
}

enum CartItemEventType {
    ADDED
    UPDATED
    REMOVED
}

enum CartConditionEventType {
    ADDED
    REMOVED
}

enum CheckoutStatus {
    PENDING
    VALIDATING
    RESERVING
    PROCESSING_PAYMENT
    FULFILLING
    COMPLETED
    FAILED
    CANCELLED
}
GRAPHQL;
    }

    /**
     * Determine if a subscriber should receive the cart update.
     *
     * @param  array<string, mixed>  $args  Subscription arguments
     */
    public function authorizeCartUpdated(mixed $subscriber, array $args): bool
    {
        return true;
    }

    /**
     * Filter cart update events by identifier/instance.
     *
     * @param  array<string, mixed>  $args
     */
    public function filterCartUpdated(mixed $event, array $args): bool
    {
        $cart = $this->getCartFromEvent($event);

        if (! $cart) {
            return false;
        }

        return $cart->getIdentifier() === $args['identifier']
            && $cart->instance() === ($args['instance'] ?? 'default');
    }

    /**
     * Transform event to cart update payload.
     *
     * @return array<string, mixed>
     */
    public function resolveCartUpdated(mixed $event): array
    {
        $cart = $this->getCartFromEvent($event);
        $eventType = $this->getEventType($event);

        return [
            'event' => $eventType,
            'cart' => $this->transformCart($cart),
            'timestamp' => now()->toIso8601String(),
            'metadata' => $this->getEventMetadata($event),
        ];
    }

    /**
     * Filter cart item change events.
     *
     * @param  array<string, mixed>  $args
     */
    public function filterCartItemChanged(mixed $event, array $args): bool
    {
        if (! in_array(get_class($event), self::CART_ITEM_EVENTS, true)) {
            return false;
        }

        $cart = $this->getCartFromEvent($event);

        if (! $cart) {
            return false;
        }

        return $cart->getIdentifier() === $args['identifier']
            && $cart->instance() === ($args['instance'] ?? 'default');
    }

    /**
     * Transform event to cart item change payload.
     *
     * @return array<string, mixed>
     */
    public function resolveCartItemChanged(mixed $event): array
    {
        $cart = $this->getCartFromEvent($event);
        $item = $this->getItemFromEvent($event);

        return [
            'event' => $this->getItemEventType($event),
            'item' => $this->transformItem($item),
            'cart' => $this->transformCart($cart),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Transform event to cart condition change payload.
     *
     * @return array<string, mixed>
     */
    public function resolveCartConditionChanged(mixed $event): array
    {
        $cart = $this->getCartFromEvent($event);

        return [
            'event' => $event instanceof CartConditionAdded ? 'ADDED' : 'REMOVED',
            'condition' => $this->getConditionFromEvent($event),
            'cart' => $this->transformCart($cart),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Build checkout status payload.
     *
     * @return array<string, mixed>
     */
    public function buildCheckoutStatusPayload(
        string $checkoutId,
        string $status,
        ?string $stage = null,
        ?string $message = null,
        ?string $orderId = null,
        ?string $paymentUrl = null
    ): array {
        return [
            'checkoutId' => $checkoutId,
            'status' => $status,
            'stage' => $stage,
            'message' => $message,
            'orderId' => $orderId,
            'paymentUrl' => $paymentUrl,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get event type enum value from event class.
     */
    private function getEventType(mixed $event): string
    {
        return match (get_class($event)) {
            ItemAdded::class => 'ITEM_ADDED',
            ItemUpdated::class => 'ITEM_UPDATED',
            ItemRemoved::class => 'ITEM_REMOVED',
            CartCleared::class => 'CART_CLEARED',
            CartConditionAdded::class => 'CONDITION_ADDED',
            CartConditionRemoved::class => 'CONDITION_REMOVED',
            default => 'ITEM_UPDATED',
        };
    }

    /**
     * Get item event type enum value.
     */
    private function getItemEventType(mixed $event): string
    {
        return match (get_class($event)) {
            ItemAdded::class => 'ADDED',
            ItemUpdated::class => 'UPDATED',
            ItemRemoved::class => 'REMOVED',
            default => 'UPDATED',
        };
    }

    /**
     * Extract cart from event.
     */
    private function getCartFromEvent(mixed $event): ?Cart
    {
        if (property_exists($event, 'cart')) {
            return $event->cart;
        }

        return null;
    }

    /**
     * Extract item from event.
     *
     * @return array<string, mixed>|null
     */
    private function getItemFromEvent(mixed $event): ?array
    {
        if ($event instanceof ItemAdded) {
            return [
                'id' => $event->item->id,
                'name' => $event->item->name,
                'price' => $event->item->price,
                'quantity' => $event->item->quantity,
            ];
        }

        if ($event instanceof ItemUpdated) {
            return [
                'id' => $event->item->id,
                'name' => $event->item->name,
                'price' => $event->item->price,
                'quantity' => $event->item->quantity,
            ];
        }

        if ($event instanceof ItemRemoved) {
            return [
                'id' => $event->item->id,
                'name' => $event->item->name,
                'price' => $event->item->price,
                'quantity' => $event->item->quantity,
            ];
        }

        return null;
    }

    /**
     * Extract condition from event.
     *
     * @return array<string, mixed>|null
     */
    private function getConditionFromEvent(mixed $event): ?array
    {
        if ($event instanceof CartConditionAdded) {
            $condition = $event->condition;

            return [
                'name' => $condition->getName(),
                'type' => $condition->getType(),
                'value' => (string) $condition->getValue(),
                'target' => $condition->getTargetDefinition()->toArray(),
            ];
        }

        if ($event instanceof CartConditionRemoved) {
            $condition = $event->condition;

            return [
                'name' => $condition->getName(),
                'type' => $condition->getType(),
                'value' => (string) $condition->getValue(),
                'target' => $condition->getTargetDefinition()->toArray(),
            ];
        }

        return null;
    }

    /**
     * Get additional metadata from event.
     *
     * @return array<string, mixed>
     */
    private function getEventMetadata(mixed $event): array
    {
        $metadata = [];

        if ($event instanceof ItemAdded) {
            $metadata['itemId'] = $event->item->id;
            $metadata['itemName'] = $event->item->name;
            $metadata['quantity'] = $event->item->quantity;
        }

        if ($event instanceof ItemRemoved) {
            $metadata['itemId'] = $event->item->id;
        }

        return $metadata;
    }

    /**
     * Transform cart to GraphQL format.
     *
     * @return array<string, mixed>
     */
    private function transformCart(?Cart $cart): array
    {
        if (! $cart) {
            return [];
        }

        $currency = config('cart.money.default_currency', 'MYR');

        return [
            'id' => $cart->getId(),
            'identifier' => $cart->getIdentifier(),
            'instance' => $cart->instance(),
            'items' => $cart->getItems()->map(fn ($item) => $this->transformItem([
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'attributes' => $item->attributes->toArray(),
            ]))->values()->toArray(),
            'itemCount' => $cart->countItems(),
            'totalQuantity' => $cart->getTotalQuantity(),
            'subtotal' => [
                'amount' => $cart->getRawSubtotal(),
                'currency' => $currency,
                'formatted' => $cart->subtotal()->format(),
            ],
            'total' => [
                'amount' => $cart->getRawTotal(),
                'currency' => $currency,
                'formatted' => $cart->total()->format(),
            ],
            'version' => $cart->getVersion(),
            'updatedAt' => now()->toISOString(),
        ];
    }

    /**
     * Transform item array to GraphQL format.
     *
     * @param  array<string, mixed>|null  $item
     * @return array<string, mixed>
     */
    private function transformItem(?array $item): array
    {
        if (! $item) {
            return [];
        }

        $currency = config('cart.money.default_currency', 'MYR');
        $price = $item['price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;

        return [
            'id' => $item['id'] ?? '',
            'name' => $item['name'] ?? '',
            'price' => [
                'amount' => $price,
                'currency' => $currency,
                'formatted' => number_format($price / 100, 2),
            ],
            'quantity' => $quantity,
            'subtotal' => [
                'amount' => $price * $quantity,
                'currency' => $currency,
                'formatted' => number_format(($price * $quantity) / 100, 2),
            ],
            'attributes' => $item['attributes'] ?? [],
        ];
    }
}
