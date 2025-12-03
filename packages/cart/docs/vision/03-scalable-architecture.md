# Cart Package Vision - Scalable Architecture

> **Document:** 03-scalable-architecture.md  
> **Series:** Cart Package Vision  
> **Focus:** Event Sourcing, CQRS, GraphQL Federation

---

## Table of Contents

1. [Event-Sourced Cart State Machine](#1-event-sourced-cart-state-machine)
2. [CQRS Pattern Implementation](#2-cqrs-pattern-implementation)
3. [GraphQL Federation API](#3-graphql-federation-api)
4. [Async Processing Pipeline](#4-async-processing-pipeline)

---

## 1. Event-Sourced Cart State Machine

### Vision Statement

Transform cart persistence from **state-based** to **event-sourced** for complete audit trails, time-travel debugging, and infinite scalability.

### Current State vs Future State

```
CURRENT: State-Based Storage
┌─────────────────────────────────────────┐
│ carts table                             │
│ ┌─────────────────────────────────────┐ │
│ │ id: uuid-123                        │ │
│ │ items: [{...}, {...}]  ← MUTABLE    │ │
│ │ conditions: [{...}]    ← MUTABLE    │ │
│ │ version: 5             ← INCREMENT  │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘

FUTURE: Event-Sourced Storage
┌─────────────────────────────────────────┐
│ cart_events table (APPEND-ONLY)         │
│ ┌─────────────────────────────────────┐ │
│ │ v1: CartCreated {id: uuid-123}      │ │
│ │ v2: ItemAdded {item: {...}}         │ │
│ │ v3: ItemAdded {item: {...}}         │ │
│ │ v4: ConditionApplied {cond: {...}}  │ │
│ │ v5: ItemQuantityChanged {qty: 3}    │ │
│ └─────────────────────────────────────┘ │
│              │                          │
│              ▼ Projection               │
│ ┌─────────────────────────────────────┐ │
│ │ cart_snapshots (Materialized View)  │ │
│ │ Current state rebuilt from events   │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### Domain Events

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events\Domain;

use AIArmada\Cart\Events\Domain\Contracts\CartDomainEvent;

abstract readonly class CartDomainEvent
{
    public function __construct(
        public string $aggregateId,           // Cart ID
        public int $aggregateVersion,         // Sequence number
        public \DateTimeImmutable $occurredAt,
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public array $metadata = [],
    ) {}
    
    abstract public function getEventType(): string;
    abstract public function getPayload(): array;
}

// Cart Lifecycle Events
final readonly class CartCreated extends CartDomainEvent
{
    public function __construct(
        string $aggregateId,
        int $aggregateVersion,
        \DateTimeImmutable $occurredAt,
        public string $identifier,
        public string $instance,
        public ?string $ownerId = null,
        public ?string $ownerType = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($aggregateId, $aggregateVersion, $occurredAt, $correlationId);
    }
    
    public function getEventType(): string
    {
        return 'cart.created';
    }
    
    public function getPayload(): array
    {
        return [
            'identifier' => $this->identifier,
            'instance' => $this->instance,
            'owner_id' => $this->ownerId,
            'owner_type' => $this->ownerType,
        ];
    }
}

// Item Events
final readonly class CartItemAdded extends CartDomainEvent
{
    public function __construct(
        string $aggregateId,
        int $aggregateVersion,
        \DateTimeImmutable $occurredAt,
        public string $itemId,
        public string $itemName,
        public int $priceInCents,
        public int $quantity,
        public array $attributes,
        public ?string $associatedModel,
        ?string $correlationId = null,
    ) {
        parent::__construct($aggregateId, $aggregateVersion, $occurredAt, $correlationId);
    }
    
    public function getEventType(): string
    {
        return 'cart.item.added';
    }
    
    public function getPayload(): array
    {
        return [
            'item_id' => $this->itemId,
            'item_name' => $this->itemName,
            'price_cents' => $this->priceInCents,
            'quantity' => $this->quantity,
            'attributes' => $this->attributes,
            'associated_model' => $this->associatedModel,
        ];
    }
}

final readonly class CartItemQuantityChanged extends CartDomainEvent
{
    public function __construct(
        string $aggregateId,
        int $aggregateVersion,
        \DateTimeImmutable $occurredAt,
        public string $itemId,
        public int $previousQuantity,
        public int $newQuantity,
        ?string $correlationId = null,
    ) {
        parent::__construct($aggregateId, $aggregateVersion, $occurredAt, $correlationId);
    }
    
    public function getEventType(): string
    {
        return 'cart.item.quantity_changed';
    }
    
    public function getPayload(): array
    {
        return [
            'item_id' => $this->itemId,
            'previous_quantity' => $this->previousQuantity,
            'new_quantity' => $this->newQuantity,
            'delta' => $this->newQuantity - $this->previousQuantity,
        ];
    }
}

final readonly class CartItemRemoved extends CartDomainEvent
{
    public function getEventType(): string
    {
        return 'cart.item.removed';
    }
    
    public function getPayload(): array
    {
        return [
            'item_id' => $this->itemId,
            'removed_quantity' => $this->quantity,
        ];
    }
}

// Condition Events
final readonly class CartConditionApplied extends CartDomainEvent
{
    public function getEventType(): string
    {
        return 'cart.condition.applied';
    }
}

final readonly class CartConditionRemoved extends CartDomainEvent
{
    public function getEventType(): string
    {
        return 'cart.condition.removed';
    }
}

// Checkout Events
final readonly class CartCheckedOut extends CartDomainEvent
{
    public function getEventType(): string
    {
        return 'cart.checked_out';
    }
}

final readonly class CartAbandoned extends CartDomainEvent
{
    public function getEventType(): string
    {
        return 'cart.abandoned';
    }
}
```

### Event Store Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\EventSourcing;

use AIArmada\Cart\Events\Domain\CartDomainEvent;

interface EventStoreInterface
{
    /**
     * Append events to the store.
     * 
     * @param array<CartDomainEvent> $events
     * @throws ConcurrencyException If expected version doesn't match
     */
    public function append(
        string $aggregateId, 
        array $events, 
        int $expectedVersion
    ): void;
    
    /**
     * Load all events for an aggregate.
     * 
     * @return iterable<CartDomainEvent>
     */
    public function load(string $aggregateId): iterable;
    
    /**
     * Load events from a specific version.
     * 
     * @return iterable<CartDomainEvent>
     */
    public function loadFromVersion(string $aggregateId, int $fromVersion): iterable;
    
    /**
     * Get the current version for an aggregate.
     */
    public function getVersion(string $aggregateId): int;
}

final class DatabaseEventStore implements EventStoreInterface
{
    public function __construct(
        private \Illuminate\Database\ConnectionInterface $connection,
        private string $table = 'cart_events',
    ) {}
    
    public function append(string $aggregateId, array $events, int $expectedVersion): void
    {
        $this->connection->transaction(function () use ($aggregateId, $events, $expectedVersion) {
            // Check current version
            $currentVersion = $this->getVersion($aggregateId);
            
            if ($currentVersion !== $expectedVersion) {
                throw new ConcurrencyException(
                    "Expected version {$expectedVersion}, but current is {$currentVersion}"
                );
            }
            
            $version = $expectedVersion;
            
            foreach ($events as $event) {
                $version++;
                
                $this->connection->table($this->table)->insert([
                    'event_id' => \Illuminate\Support\Str::uuid(),
                    'aggregate_id' => $aggregateId,
                    'aggregate_version' => $version,
                    'event_type' => $event->getEventType(),
                    'event_data' => json_encode($event->getPayload()),
                    'metadata' => json_encode([
                        'correlation_id' => $event->correlationId,
                        'causation_id' => $event->causationId,
                        ...$event->metadata,
                    ]),
                    'created_at' => $event->occurredAt,
                ]);
            }
        });
    }
    
    public function load(string $aggregateId): iterable
    {
        return $this->connection->table($this->table)
            ->where('aggregate_id', $aggregateId)
            ->orderBy('aggregate_version')
            ->cursor()
            ->map(fn ($row) => $this->deserializeEvent($row));
    }
    
    public function getVersion(string $aggregateId): int
    {
        return (int) $this->connection->table($this->table)
            ->where('aggregate_id', $aggregateId)
            ->max('aggregate_version') ?? 0;
    }
    
    private function deserializeEvent(object $row): CartDomainEvent
    {
        // Event type to class mapping
        $eventClass = match ($row->event_type) {
            'cart.created' => CartCreated::class,
            'cart.item.added' => CartItemAdded::class,
            'cart.item.quantity_changed' => CartItemQuantityChanged::class,
            'cart.item.removed' => CartItemRemoved::class,
            'cart.condition.applied' => CartConditionApplied::class,
            'cart.condition.removed' => CartConditionRemoved::class,
            'cart.checked_out' => CartCheckedOut::class,
            'cart.abandoned' => CartAbandoned::class,
            default => throw new \RuntimeException("Unknown event type: {$row->event_type}"),
        };
        
        $payload = json_decode($row->event_data, true);
        $metadata = json_decode($row->metadata ?? '{}', true);
        
        return new $eventClass(
            aggregateId: $row->aggregate_id,
            aggregateVersion: $row->aggregate_version,
            occurredAt: new \DateTimeImmutable($row->created_at),
            correlationId: $metadata['correlation_id'] ?? null,
            ...$payload
        );
    }
}
```

### Aggregate Root Pattern

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\EventSourcing;

abstract class AggregateRoot
{
    protected string $id;
    protected int $version = 0;
    protected array $uncommittedEvents = [];
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getVersion(): int
    {
        return $this->version;
    }
    
    /**
     * @return array<CartDomainEvent>
     */
    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }
    
    public function clearUncommittedEvents(): void
    {
        $this->uncommittedEvents = [];
    }
    
    protected function recordEvent(CartDomainEvent $event): void
    {
        $this->uncommittedEvents[] = $event;
        $this->apply($event);
    }
    
    abstract protected function apply(CartDomainEvent $event): void;
    
    public static function reconstitute(iterable $events): static
    {
        $aggregate = new static();
        
        foreach ($events as $event) {
            $aggregate->apply($event);
            $aggregate->version = $event->aggregateVersion;
        }
        
        return $aggregate;
    }
}
```

### Database Schema for Event Store

```sql
-- EVENT STORE (append-only, immutable)
CREATE TABLE cart_events (
    event_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    aggregate_id UUID NOT NULL,
    aggregate_version INTEGER NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_data JSONB NOT NULL,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    
    -- Ensures event ordering and prevents duplicates
    CONSTRAINT unique_aggregate_version 
        UNIQUE (aggregate_id, aggregate_version)
);

-- Optimized indexes
CREATE INDEX idx_cart_events_aggregate 
    ON cart_events(aggregate_id);
CREATE INDEX idx_cart_events_type 
    ON cart_events(event_type);
CREATE INDEX idx_cart_events_created 
    ON cart_events(created_at);

-- GIN index for querying event data
CREATE INDEX idx_cart_events_data_gin 
    ON cart_events USING GIN (event_data);

-- SNAPSHOTS for performance optimization
CREATE TABLE cart_snapshots (
    cart_id UUID PRIMARY KEY,
    version INTEGER NOT NULL,
    state JSONB NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Snapshot every N events or on checkout
CREATE INDEX idx_cart_snapshots_version 
    ON cart_snapshots(cart_id, version);
```

---

## 2. CQRS Pattern Implementation

### Vision Statement

Separate **read and write models** to optimize each path independently, enabling different storage technologies for different access patterns.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    CQRS CART ARCHITECTURE                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│         WRITE SIDE                      READ SIDE              │
│         ──────────                      ─────────              │
│                                                                 │
│  ┌─────────────────┐              ┌─────────────────────────┐  │
│  │ Command Handler │              │    Query Handlers       │  │
│  │                 │              │                         │  │
│  │ - AddItem       │              │ - GetCartSummary       │  │
│  │ - UpdateQty     │              │ - GetCartForCheckout   │  │
│  │ - ApplyDiscount │              │ - GetAbandonedCarts    │  │
│  └────────┬────────┘              │ - SearchCartsBy...     │  │
│           │                       └───────────┬─────────────┘  │
│           ▼                                   │                │
│  ┌─────────────────┐              ┌───────────▼─────────────┐  │
│  │ Write Model     │   ────────►  │    Read Model(s)       │  │
│  │ (PostgreSQL)    │   Event      │                         │  │
│  │                 │   Sync       │  - Redis (hot data)    │  │
│  │ - Normalized    │              │  - Elasticsearch       │  │
│  │ - Transactional │              │  - Materialized Views  │  │
│  └─────────────────┘              └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Command Layer

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Application\Commands;

// Commands (Intent)
final readonly class AddItemToCartCommand
{
    public function __construct(
        public string $cartId,
        public string $itemId,
        public string $itemName,
        public int $priceInCents,
        public int $quantity,
        public array $attributes = [],
        public ?string $associatedModel = null,
    ) {}
}

final readonly class UpdateItemQuantityCommand
{
    public function __construct(
        public string $cartId,
        public string $itemId,
        public int $newQuantity,
    ) {}
}

final readonly class ApplyConditionCommand
{
    public function __construct(
        public string $cartId,
        public string $conditionName,
        public string $conditionType,
        public string $value,
        public array $targetDefinition,
    ) {}
}

// Command Handler
final class CartCommandHandler
{
    public function __construct(
        private CartRepository $repository,
        private EventStoreInterface $eventStore,
    ) {}
    
    public function handleAddItem(AddItemToCartCommand $command): void
    {
        $cart = $this->repository->getById($command->cartId);
        
        $cart->addItem(
            $command->itemId,
            $command->itemName,
            $command->priceInCents,
            $command->quantity,
            $command->attributes,
            $command->associatedModel
        );
        
        $this->repository->save($cart);
    }
    
    public function handleUpdateQuantity(UpdateItemQuantityCommand $command): void
    {
        $cart = $this->repository->getById($command->cartId);
        
        $cart->updateItemQuantity($command->itemId, $command->newQuantity);
        
        $this->repository->save($cart);
    }
}
```

### Query Layer

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Application\Queries;

// Queries
final readonly class GetCartSummaryQuery
{
    public function __construct(
        public string $cartId,
    ) {}
}

final readonly class GetAbandonedCartsQuery
{
    public function __construct(
        public \DateTimeImmutable $olderThan,
        public ?int $minValueCents = null,
        public int $limit = 100,
    ) {}
}

final readonly class SearchCartsQuery
{
    public function __construct(
        public ?string $identifier = null,
        public ?string $instance = null,
        public ?\DateTimeImmutable $createdAfter = null,
        public ?\DateTimeImmutable $createdBefore = null,
        public ?int $minItems = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {}
}

// Query Handler using Read Model
final class CartQueryHandler
{
    public function __construct(
        private CartReadModel $readModel,
    ) {}
    
    public function handleGetSummary(GetCartSummaryQuery $query): ?CartSummaryDTO
    {
        return $this->readModel->getCartSummary($query->cartId);
    }
    
    public function handleGetAbandoned(GetAbandonedCartsQuery $query): array
    {
        return $this->readModel->getAbandonedCarts(
            $query->olderThan,
            $query->minValueCents,
            $query->limit
        );
    }
}

// Read Model Interface
interface CartReadModel
{
    public function getCartSummary(string $cartId): ?CartSummaryDTO;
    public function getAbandonedCarts(\DateTimeImmutable $olderThan, ?int $minValue, int $limit): array;
    public function searchCarts(SearchCartsQuery $query): PaginatedResult;
}

// Redis-backed Read Model for hot data
final class RedisCartReadModel implements CartReadModel
{
    public function __construct(
        private \Redis $redis,
        private string $prefix = 'cart:read:',
    ) {}
    
    public function getCartSummary(string $cartId): ?CartSummaryDTO
    {
        $data = $this->redis->hGetAll("{$this->prefix}{$cartId}");
        
        if (empty($data)) {
            return null;
        }
        
        return CartSummaryDTO::fromArray($data);
    }
    
    // Projection handler - updates read model when events occur
    public function project(CartDomainEvent $event): void
    {
        match ($event->getEventType()) {
            'cart.item.added' => $this->projectItemAdded($event),
            'cart.item.removed' => $this->projectItemRemoved($event),
            'cart.item.quantity_changed' => $this->projectQuantityChanged($event),
            default => null,
        };
    }
    
    private function projectItemAdded(CartItemAdded $event): void
    {
        $key = "{$this->prefix}{$event->aggregateId}";
        
        $this->redis->hIncrBy($key, 'item_count', 1);
        $this->redis->hIncrBy($key, 'total_quantity', $event->quantity);
        $this->redis->hIncrBy($key, 'subtotal_cents', $event->priceInCents * $event->quantity);
        $this->redis->hSet($key, 'updated_at', now()->toIso8601String());
        
        // Set TTL for automatic cleanup
        $this->redis->expire($key, 86400 * 30); // 30 days
    }
}
```

### DTOs for Read Model

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Application\DTOs;

final readonly class CartSummaryDTO
{
    public function __construct(
        public string $cartId,
        public string $identifier,
        public string $instance,
        public int $itemCount,
        public int $totalQuantity,
        public int $subtotalCents,
        public int $totalCents,
        public int $savingsCents,
        public int $conditionCount,
        public ?string $shippingMethod,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            cartId: $data['cart_id'],
            identifier: $data['identifier'],
            instance: $data['instance'],
            itemCount: (int) $data['item_count'],
            totalQuantity: (int) $data['total_quantity'],
            subtotalCents: (int) $data['subtotal_cents'],
            totalCents: (int) $data['total_cents'],
            savingsCents: (int) ($data['savings_cents'] ?? 0),
            conditionCount: (int) ($data['condition_count'] ?? 0),
            shippingMethod: $data['shipping_method'] ?? null,
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
        );
    }
}
```

---

## 3. GraphQL Federation API

### Vision Statement

Provide a **federated GraphQL API** for headless commerce, enabling frontend flexibility and efficient data fetching.

### Schema

```graphql
# Cart Federation Schema
# File: schema.graphql

extend schema
  @link(url: "https://specs.apollo.dev/federation/v2.0",
        import: ["@key", "@shareable", "@provides", "@external"])

type Cart @key(fields: "id") {
  id: ID!
  identifier: String!
  instance: String!
  
  # Items
  items: [CartItem!]!
  itemCount: Int!
  totalQuantity: Int!
  
  # Conditions
  conditions: [CartCondition!]!
  discounts: [CartCondition!]!
  taxes: [CartCondition!]!
  
  # Totals
  subtotal: Money!
  total: Money!
  savings: Money!
  
  # Shipping
  shipping: ShippingInfo
  shippingMethod: String
  
  # Metadata
  metadata: JSON
  
  # Versioning
  version: Int!
  createdAt: DateTime!
  updatedAt: DateTime!
}

type CartItem @key(fields: "id cartId") {
  id: ID!
  cartId: ID!
  name: String!
  price: Money!
  quantity: Int!
  subtotal: Money!
  
  # Associated product (federated from Product service)
  product: Product @provides(fields: "id sku name")
  
  # Item conditions
  conditions: [ItemCondition!]!
  
  # Attributes
  attributes: JSON
}

type CartCondition {
  name: String!
  type: ConditionType!
  value: String!
  calculatedValue: Money!
  isDiscount: Boolean!
  isPercentage: Boolean!
  order: Int!
}

type Money {
  amount: Int!
  currency: String!
  formatted: String!
}

type ShippingInfo {
  method: String!
  cost: Money!
  estimatedDelivery: DateTime
  carrier: String
}

enum ConditionType {
  DISCOUNT
  TAX
  FEE
  SHIPPING
  CUSTOM
}

# Mutations
type Mutation {
  # Cart mutations
  createCart(input: CreateCartInput!): CartMutationResult!
  
  # Item mutations
  addToCart(input: AddToCartInput!): CartMutationResult!
  updateCartItem(input: UpdateCartItemInput!): CartMutationResult!
  removeFromCart(cartId: ID!, itemId: ID!): CartMutationResult!
  
  # Condition mutations
  applyCondition(input: ApplyConditionInput!): CartMutationResult!
  removeCondition(cartId: ID!, conditionName: String!): CartMutationResult!
  
  # Shipping
  setShipping(input: SetShippingInput!): CartMutationResult!
  
  # Cart lifecycle
  clearCart(cartId: ID!): CartMutationResult!
  checkout(cartId: ID!): CheckoutResult!
}

input CreateCartInput {
  identifier: String
  instance: String = "default"
}

input AddToCartInput {
  cartId: ID!
  itemId: ID!
  name: String!
  priceInCents: Int!
  quantity: Int = 1
  attributes: JSON
  productId: ID
}

input UpdateCartItemInput {
  cartId: ID!
  itemId: ID!
  quantity: Int
  attributes: JSON
}

input ApplyConditionInput {
  cartId: ID!
  name: String!
  type: ConditionType!
  value: String!
  target: String = "cart@cart_subtotal/aggregate"
}

input SetShippingInput {
  cartId: ID!
  method: String!
  cost: Int!
  carrier: String
  estimatedDelivery: DateTime
}

type CartMutationResult {
  success: Boolean!
  cart: Cart
  errors: [CartError!]
}

type CheckoutResult {
  success: Boolean!
  orderId: ID
  paymentUrl: String
  errors: [CartError!]
}

type CartError {
  code: String!
  message: String!
  field: String
}

# Queries
type Query {
  # Get cart by ID
  cart(id: ID!): Cart
  
  # Get cart by identifier and instance
  cartByIdentifier(identifier: String!, instance: String = "default"): Cart
  
  # Get current user's cart
  myCart(instance: String = "default"): Cart
  
  # Admin queries
  abandonedCarts(
    olderThan: DateTime!
    minValueCents: Int
    limit: Int = 50
  ): [Cart!]!
}

# Subscriptions for real-time updates
type Subscription {
  cartUpdated(cartId: ID!): Cart!
  cartItemChanged(cartId: ID!): CartItem!
}
```

### GraphQL Resolver Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\GraphQL\Resolvers;

use AIArmada\Cart\Application\Commands\AddItemToCartCommand;
use AIArmada\Cart\Application\Queries\GetCartSummaryQuery;
use AIArmada\Cart\CartManager;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class CartResolver
{
    public function __construct(
        private CartManager $cartManager,
        private CartCommandHandler $commandHandler,
        private CartQueryHandler $queryHandler,
    ) {}
    
    // Queries
    public function cart($root, array $args, GraphQLContext $context, ResolveInfo $info): ?array
    {
        $query = new GetCartSummaryQuery($args['id']);
        $summary = $this->queryHandler->handleGetSummary($query);
        
        if (!$summary) {
            return null;
        }
        
        return $this->transformToGraphQL($summary);
    }
    
    public function myCart($root, array $args, GraphQLContext $context, ResolveInfo $info): ?array
    {
        $user = $context->user();
        if (!$user) {
            return null;
        }
        
        $instance = $args['instance'] ?? 'default';
        $cart = $this->cartManager
            ->setIdentifier((string) $user->id)
            ->setInstance($instance)
            ->getCurrentCart();
        
        return $this->transformCartToGraphQL($cart);
    }
    
    // Mutations
    public function addToCart($root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        $input = $args['input'];
        
        try {
            $command = new AddItemToCartCommand(
                cartId: $input['cartId'],
                itemId: $input['itemId'],
                itemName: $input['name'],
                priceInCents: $input['priceInCents'],
                quantity: $input['quantity'] ?? 1,
                attributes: $input['attributes'] ?? [],
                associatedModel: $input['productId'] ?? null,
            );
            
            $this->commandHandler->handleAddItem($command);
            
            return [
                'success' => true,
                'cart' => $this->cart(null, ['id' => $input['cartId']], $context, $info),
                'errors' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'ADD_ITEM_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }
    
    private function transformCartToGraphQL(\AIArmada\Cart\Cart $cart): array
    {
        return [
            'id' => $cart->getId(),
            'identifier' => $cart->getIdentifier(),
            'instance' => $cart->instance(),
            'items' => $cart->getItems()->map(fn ($item) => [
                'id' => $item->id,
                'cartId' => $cart->getId(),
                'name' => $item->name,
                'price' => [
                    'amount' => $item->price,
                    'currency' => config('cart.money.default_currency'),
                    'formatted' => $item->getPrice()->format(),
                ],
                'quantity' => $item->quantity,
                'subtotal' => [
                    'amount' => $item->getRawSubtotal(),
                    'currency' => config('cart.money.default_currency'),
                    'formatted' => $item->getSubtotal()->format(),
                ],
                'attributes' => $item->attributes->toArray(),
            ])->values()->toArray(),
            'itemCount' => $cart->countItems(),
            'totalQuantity' => $cart->getTotalQuantity(),
            'subtotal' => [
                'amount' => $cart->getRawSubtotal(),
                'currency' => config('cart.money.default_currency'),
                'formatted' => $cart->subtotal()->format(),
            ],
            'total' => [
                'amount' => $cart->getRawTotal(),
                'currency' => config('cart.money.default_currency'),
                'formatted' => $cart->total()->format(),
            ],
            'version' => $cart->getVersion(),
        ];
    }
}
```

---

## 4. Async Processing Pipeline

### Vision Statement

Handle heavy cart operations **asynchronously** to maintain responsiveness and enable complex workflows.

### Job Classes

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecalculateCartTotalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public string $cartId,
        public bool $notifyCustomer = false,
    ) {}
    
    public function handle(CartManager $cartManager): void
    {
        $cart = $cartManager->getById($this->cartId);
        
        if (!$cart) {
            return;
        }
        
        // Force recalculation by evaluating pipeline
        $result = $cart->evaluateConditionPipeline();
        
        if ($this->notifyCustomer) {
            // Dispatch notification
            CustomerCartUpdatedNotification::dispatch($cart);
        }
    }
}

final class ProcessAbandonedCartsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public int $hoursThreshold = 24,
    ) {}
    
    public function handle(CartQueryHandler $queryHandler): void
    {
        $abandonedCarts = $queryHandler->handleGetAbandoned(
            new GetAbandonedCartsQuery(
                olderThan: now()->subHours($this->hoursThreshold),
                minValueCents: 1000, // $10 minimum
                limit: 1000,
            )
        );
        
        foreach ($abandonedCarts as $cart) {
            AbandonedCartReminderJob::dispatch($cart['id'])
                ->delay(now()->addMinutes(rand(0, 60))); // Stagger
        }
    }
}

final class SyncCartToExternalSystemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public int $backoff = 60;
    
    public function __construct(
        public string $cartId,
        public string $externalSystem, // 'crm', 'analytics', 'erp'
    ) {}
    
    public function handle(): void
    {
        $adapter = match ($this->externalSystem) {
            'crm' => app(CRMCartSyncAdapter::class),
            'analytics' => app(AnalyticsCartSyncAdapter::class),
            'erp' => app(ERPCartSyncAdapter::class),
            default => throw new \InvalidArgumentException("Unknown system: {$this->externalSystem}"),
        };
        
        $adapter->syncCart($this->cartId);
    }
}
```

---

## Summary: Architecture Priority Matrix

| Component | Complexity | Impact | Dependencies | Phase |
|-----------|------------|--------|--------------|-------|
| Event Store Schema | Medium | Critical | None | **Phase 1** |
| Domain Events | Low | High | Event Store | **Phase 1** |
| Event Store Implementation | Medium | Critical | Schema | **Phase 1** |
| Projections (Read Model) | Medium | High | Events | **Phase 1** |
| CQRS Commands | Low | Medium | Events | **Phase 2** |
| CQRS Queries | Low | Medium | Projections | **Phase 2** |
| GraphQL Schema | Medium | High | CQRS | **Phase 2** |
| GraphQL Resolvers | Medium | High | Schema | **Phase 2** |
| Async Jobs | Low | Medium | None | **Phase 1** |

---

**Next:** [04-future-proof-structure.md](04-future-proof-structure.md) - Hexagonal Architecture, DDD Bounded Contexts
