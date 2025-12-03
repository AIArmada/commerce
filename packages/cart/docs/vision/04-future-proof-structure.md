# Cart Package Vision - Future-Proof Structure

> **Document:** 04-future-proof-structure.md  
> **Series:** Cart Package Vision  
> **Focus:** Hexagonal Architecture, DDD Bounded Contexts

---

## Table of Contents

1. [Hexagonal Architecture (Ports & Adapters)](#1-hexagonal-architecture-ports--adapters)
2. [Domain-Driven Design Bounded Contexts](#2-domain-driven-design-bounded-contexts)
3. [Module Decomposition Strategy](#3-module-decomposition-strategy)
4. [Dependency Inversion Patterns](#4-dependency-inversion-patterns)

---

## 1. Hexagonal Architecture (Ports & Adapters)

### Vision Statement

Restructure the package using **hexagonal architecture** to isolate business logic from infrastructure concerns, enabling framework independence and easier testing.

### Current Structure

```
src/
├── Cart.php                    # Mixed concerns
├── CartManager.php             # Mixed concerns
├── Storage/                    # Infrastructure
│   ├── DatabaseStorage.php
│   ├── SessionStorage.php
│   └── CacheStorage.php
├── Conditions/                 # Domain + Infrastructure mixed
│   ├── CartCondition.php
│   ├── Pipeline/
│   └── Enums/
├── Services/                   # Application + Infrastructure mixed
│   ├── CartMigrationService.php
│   └── TaxCalculator.php
├── Models/                     # Domain
│   └── CartItem.php
└── Events/                     # Domain
    └── *.php
```

### Proposed Hexagonal Structure

```
src/
├── Domain/                          # Core Business Logic (Zero Dependencies)
│   ├── Aggregates/
│   │   ├── Cart.php                 # Cart Aggregate Root
│   │   ├── CartItem.php             # Entity
│   │   └── CartCondition.php        # Entity
│   ├── ValueObjects/
│   │   ├── CartId.php
│   │   ├── ItemId.php
│   │   ├── Money.php
│   │   ├── Quantity.php
│   │   ├── ConditionValue.php
│   │   └── ConditionTarget.php
│   ├── Events/
│   │   ├── CartCreated.php
│   │   ├── ItemAdded.php
│   │   ├── ItemRemoved.php
│   │   ├── ConditionApplied.php
│   │   └── CartCheckedOut.php
│   ├── Services/
│   │   ├── PricingService.php       # Domain service
│   │   ├── ConditionEvaluator.php   # Domain service
│   │   └── TaxCalculator.php        # Domain service
│   ├── Policies/
│   │   ├── CartPolicy.php           # Business rules
│   │   └── CheckoutPolicy.php
│   ├── Exceptions/
│   │   ├── CartException.php
│   │   ├── InvalidItemException.php
│   │   └── CheckoutException.php
│   └── Contracts/
│       ├── CartRepositoryInterface.php
│       ├── CartReadModelInterface.php
│       ├── EventPublisherInterface.php
│       └── PricingStrategyInterface.php
│
├── Application/                     # Use Cases (Orchestration)
│   ├── Commands/
│   │   ├── AddItemCommand.php
│   │   ├── AddItemHandler.php
│   │   ├── UpdateQuantityCommand.php
│   │   ├── UpdateQuantityHandler.php
│   │   ├── ApplyConditionCommand.php
│   │   ├── ApplyConditionHandler.php
│   │   ├── CheckoutCommand.php
│   │   └── CheckoutHandler.php
│   ├── Queries/
│   │   ├── GetCartQuery.php
│   │   ├── GetCartHandler.php
│   │   ├── GetAbandonedCartsQuery.php
│   │   └── GetAbandonedCartsHandler.php
│   ├── DTOs/
│   │   ├── CartDTO.php
│   │   ├── CartItemDTO.php
│   │   ├── CheckoutDTO.php
│   │   └── CartSummaryDTO.php
│   ├── Listeners/
│   │   ├── UpdateReadModelOnItemAdded.php
│   │   └── NotifyOnCartAbandoned.php
│   └── Contracts/
│       ├── CommandBusInterface.php
│       └── QueryBusInterface.php
│
├── Infrastructure/                  # External Adapters
│   ├── Persistence/
│   │   ├── EloquentCartRepository.php
│   │   ├── EventStoreCartRepository.php
│   │   ├── RedisCartReadModel.php
│   │   └── EloquentCartReadModel.php
│   ├── Storage/
│   │   ├── DatabaseStorage.php
│   │   ├── SessionStorage.php
│   │   └── CacheStorage.php
│   ├── Messaging/
│   │   ├── LaravelEventPublisher.php
│   │   ├── KafkaEventPublisher.php
│   │   └── RabbitMQEventPublisher.php
│   ├── HTTP/
│   │   ├── Controllers/
│   │   │   └── CartController.php
│   │   ├── Requests/
│   │   │   ├── AddItemRequest.php
│   │   │   └── UpdateQuantityRequest.php
│   │   └── Resources/
│   │       ├── CartResource.php
│   │       └── CartItemResource.php
│   ├── GraphQL/
│   │   ├── Resolvers/
│   │   │   └── CartResolver.php
│   │   ├── Types/
│   │   │   └── CartType.php
│   │   └── Mutations/
│   │       └── CartMutations.php
│   └── External/
│       ├── StripeCheckoutAdapter.php
│       ├── InventoryServiceAdapter.php
│       └── AnalyticsAdapter.php
│
├── Presentation/                    # UI Layer (Filament, Blade, etc.)
│   ├── Filament/
│   │   ├── Resources/
│   │   │   └── CartResource.php
│   │   ├── Pages/
│   │   │   └── CartDashboard.php
│   │   └── Widgets/
│   │       └── CartStatsWidget.php
│   ├── Livewire/
│   │   ├── CartComponent.php
│   │   └── CartItemComponent.php
│   └── Blade/
│       └── Components/
│           └── CartSummary.php
│
├── Facades/
│   └── Cart.php                     # Laravel Facade
│
└── CartServiceProvider.php          # Wiring everything together
```

### Domain Layer Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Domain\Aggregates;

use AIArmada\Cart\Domain\Contracts\CartRepositoryInterface;
use AIArmada\Cart\Domain\Events\CartCreated;
use AIArmada\Cart\Domain\Events\ItemAdded;
use AIArmada\Cart\Domain\Events\ItemRemoved;
use AIArmada\Cart\Domain\Exceptions\InvalidItemException;
use AIArmada\Cart\Domain\ValueObjects\CartId;
use AIArmada\Cart\Domain\ValueObjects\ItemId;
use AIArmada\Cart\Domain\ValueObjects\Money;
use AIArmada\Cart\Domain\ValueObjects\Quantity;

/**
 * Cart Aggregate Root - The heart of cart business logic.
 * 
 * This class has ZERO framework dependencies. It's pure PHP.
 * All persistence, HTTP, and framework concerns are in Infrastructure.
 */
final class Cart
{
    /** @var array<string, CartItem> */
    private array $items = [];
    
    /** @var array<string, CartCondition> */
    private array $conditions = [];
    
    /** @var array<CartDomainEvent> */
    private array $domainEvents = [];
    
    private int $version = 0;
    
    private function __construct(
        private CartId $id,
        private string $identifier,
        private string $instance,
        private ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt ??= new \DateTimeImmutable();
    }
    
    /**
     * Named constructor for creating a new cart.
     */
    public static function create(
        CartId $id,
        string $identifier,
        string $instance = 'default'
    ): self {
        $cart = new self($id, $identifier, $instance);
        
        $cart->recordEvent(new CartCreated(
            aggregateId: $id->toString(),
            identifier: $identifier,
            instance: $instance,
            occurredAt: new \DateTimeImmutable(),
        ));
        
        return $cart;
    }
    
    /**
     * Add item to cart - core business logic.
     */
    public function addItem(
        ItemId $itemId,
        string $name,
        Money $price,
        Quantity $quantity,
        array $attributes = []
    ): self {
        // Business rule: Validate item
        if ($price->isNegative()) {
            throw InvalidItemException::negativePriceNotAllowed($itemId);
        }
        
        if ($quantity->isZero()) {
            throw InvalidItemException::zeroQuantityNotAllowed($itemId);
        }
        
        // Business rule: If item exists, increase quantity
        if (isset($this->items[$itemId->toString()])) {
            $existingItem = $this->items[$itemId->toString()];
            $newQuantity = $existingItem->quantity()->add($quantity);
            
            $this->items[$itemId->toString()] = $existingItem->withQuantity($newQuantity);
        } else {
            $this->items[$itemId->toString()] = new CartItem(
                $itemId,
                $name,
                $price,
                $quantity,
                $attributes
            );
        }
        
        $this->recordEvent(new ItemAdded(
            aggregateId: $this->id->toString(),
            itemId: $itemId->toString(),
            name: $name,
            priceInCents: $price->getAmountInCents(),
            quantity: $quantity->getValue(),
            occurredAt: new \DateTimeImmutable(),
        ));
        
        return $this;
    }
    
    /**
     * Remove item from cart.
     */
    public function removeItem(ItemId $itemId): self
    {
        if (!isset($this->items[$itemId->toString()])) {
            return $this; // Idempotent - no error if item doesn't exist
        }
        
        $removedItem = $this->items[$itemId->toString()];
        unset($this->items[$itemId->toString()]);
        
        $this->recordEvent(new ItemRemoved(
            aggregateId: $this->id->toString(),
            itemId: $itemId->toString(),
            occurredAt: new \DateTimeImmutable(),
        ));
        
        return $this;
    }
    
    /**
     * Update item quantity.
     */
    public function updateQuantity(ItemId $itemId, Quantity $newQuantity): self
    {
        if (!isset($this->items[$itemId->toString()])) {
            throw InvalidItemException::itemNotFound($itemId);
        }
        
        if ($newQuantity->isZero()) {
            return $this->removeItem($itemId);
        }
        
        $this->items[$itemId->toString()] = $this->items[$itemId->toString()]
            ->withQuantity($newQuantity);
        
        return $this;
    }
    
    /**
     * Calculate subtotal - pure business logic.
     */
    public function calculateSubtotal(): Money
    {
        $total = Money::zero();
        
        foreach ($this->items as $item) {
            $total = $total->add($item->getLineTotal());
        }
        
        return $total;
    }
    
    /**
     * Get all pending domain events.
     * 
     * @return array<CartDomainEvent>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
    
    private function recordEvent(CartDomainEvent $event): void
    {
        $this->domainEvents[] = $event;
        $this->version++;
    }
    
    // Getters
    public function getId(): CartId { return $this->id; }
    public function getIdentifier(): string { return $this->identifier; }
    public function getInstance(): string { return $this->instance; }
    public function getVersion(): int { return $this->version; }
    public function getItems(): array { return $this->items; }
    public function isEmpty(): bool { return empty($this->items); }
}
```

### Value Objects

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Domain\ValueObjects;

/**
 * Cart ID Value Object - Immutable identifier.
 */
final readonly class CartId
{
    private function __construct(
        private string $value,
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Cart ID cannot be empty');
        }
    }
    
    public static function generate(): self
    {
        return new self(\Ramsey\Uuid\Uuid::uuid4()->toString());
    }
    
    public static function fromString(string $value): self
    {
        return new self($value);
    }
    
    public function toString(): string
    {
        return $this->value;
    }
    
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

/**
 * Money Value Object - Handles currency calculations.
 */
final readonly class Money
{
    private function __construct(
        private int $amountInCents,
        private string $currency = 'MYR',
    ) {}
    
    public static function fromCents(int $cents, string $currency = 'MYR'): self
    {
        return new self($cents, $currency);
    }
    
    public static function fromDecimal(float $amount, string $currency = 'MYR'): self
    {
        return new self((int) round($amount * 100), $currency);
    }
    
    public static function zero(string $currency = 'MYR'): self
    {
        return new self(0, $currency);
    }
    
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }
    
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }
    
    public function multiply(int $factor): self
    {
        return new self($this->amountInCents * $factor, $this->currency);
    }
    
    public function isNegative(): bool
    {
        return $this->amountInCents < 0;
    }
    
    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }
    
    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }
    
    public function getCurrency(): string
    {
        return $this->currency;
    }
    
    public function format(): string
    {
        $amount = $this->amountInCents / 100;
        return number_format($amount, 2) . ' ' . $this->currency;
    }
    
    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }
}

/**
 * Quantity Value Object - Ensures valid quantities.
 */
final readonly class Quantity
{
    private function __construct(
        private int $value,
    ) {
        if ($value < 0) {
            throw new \InvalidArgumentException('Quantity cannot be negative');
        }
    }
    
    public static function of(int $value): self
    {
        return new self($value);
    }
    
    public static function one(): self
    {
        return new self(1);
    }
    
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }
    
    public function subtract(self $other): self
    {
        return new self(max(0, $this->value - $other->value));
    }
    
    public function isZero(): bool
    {
        return $this->value === 0;
    }
    
    public function getValue(): int
    {
        return $this->value;
    }
}
```

### Port Interfaces (Domain Contracts)

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Domain\Contracts;

use AIArmada\Cart\Domain\Aggregates\Cart;
use AIArmada\Cart\Domain\ValueObjects\CartId;

/**
 * Port: Cart Repository - The domain defines WHAT it needs.
 * Infrastructure provides HOW.
 */
interface CartRepositoryInterface
{
    public function getById(CartId $id): ?Cart;
    
    public function getByIdentifier(string $identifier, string $instance): ?Cart;
    
    public function save(Cart $cart): void;
    
    public function delete(CartId $id): void;
    
    public function nextIdentity(): CartId;
}

/**
 * Port: Event Publisher - Domain says "publish this event".
 * Infrastructure decides how (Laravel events, Kafka, etc.)
 */
interface EventPublisherInterface
{
    /**
     * @param array<CartDomainEvent> $events
     */
    public function publish(array $events): void;
}

/**
 * Port: Cart Read Model - Optimized for queries.
 */
interface CartReadModelInterface
{
    public function getCartSummary(string $cartId): ?CartSummaryDTO;
    
    public function getAbandonedCarts(\DateTimeImmutable $olderThan, int $limit): array;
    
    public function searchCarts(array $criteria): array;
}
```

### Infrastructure Adapters

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Infrastructure\Persistence;

use AIArmada\Cart\Domain\Aggregates\Cart;
use AIArmada\Cart\Domain\Contracts\CartRepositoryInterface;
use AIArmada\Cart\Domain\ValueObjects\CartId;
use Illuminate\Database\ConnectionInterface;

/**
 * Adapter: Eloquent implementation of CartRepository.
 */
final class EloquentCartRepository implements CartRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'carts',
    ) {}
    
    public function getById(CartId $id): ?Cart
    {
        $row = $this->connection->table($this->table)
            ->where('id', $id->toString())
            ->first();
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrate($row);
    }
    
    public function getByIdentifier(string $identifier, string $instance): ?Cart
    {
        $row = $this->connection->table($this->table)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->first();
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrate($row);
    }
    
    public function save(Cart $cart): void
    {
        $this->connection->table($this->table)->updateOrInsert(
            ['id' => $cart->getId()->toString()],
            [
                'identifier' => $cart->getIdentifier(),
                'instance' => $cart->getInstance(),
                'items' => json_encode($this->serializeItems($cart->getItems())),
                'version' => $cart->getVersion(),
                'updated_at' => now(),
            ]
        );
    }
    
    public function delete(CartId $id): void
    {
        $this->connection->table($this->table)
            ->where('id', $id->toString())
            ->delete();
    }
    
    public function nextIdentity(): CartId
    {
        return CartId::generate();
    }
    
    private function hydrate(object $row): Cart
    {
        // Reconstitute Cart from database row
        // ...implementation
    }
    
    private function serializeItems(array $items): array
    {
        return array_map(fn ($item) => $item->toArray(), $items);
    }
}
```

---

## 2. Domain-Driven Design Bounded Contexts

### Vision Statement

Define clear **bounded contexts** for the commerce domain, with explicit boundaries and integration patterns between contexts.

### Context Map

```
┌─────────────────────────────────────────────────────────────────┐
│                    COMMERCE BOUNDED CONTEXTS                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────┐  │
│  │   CART CONTEXT   │  │ PRICING CONTEXT  │  │  INVENTORY   │  │
│  │   (Core)         │  │ (Supporting)     │  │  CONTEXT     │  │
│  │                  │  │                  │  │ (Supporting) │  │
│  │ • Cart           │  │ • PriceRule      │  │              │  │
│  │ • CartItem       │  │ • Discount       │  │ • Stock      │  │
│  │ • Condition      │  │ • Tax            │  │ • Reservation│  │
│  │                  │  │ • DynamicPrice   │  │ • Warehouse  │  │
│  └────────┬─────────┘  └────────┬─────────┘  └──────┬───────┘  │
│           │                     │                   │          │
│           │    Published        │   Domain          │          │
│           │    Language         │   Events          │          │
│           └─────────────────────┼───────────────────┘          │
│                                 ▼                              │
│                    ┌─────────────────────────┐                 │
│                    │    ANTI-CORRUPTION      │                 │
│                    │         LAYER           │                 │
│                    │  (Translation/Mapping)  │                 │
│                    └─────────────────────────┘                 │
│                                 │                              │
│           ┌─────────────────────┼───────────────────┐          │
│           ▼                     ▼                   ▼          │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────┐  │
│  │  ORDER CONTEXT   │  │ PAYMENT CONTEXT  │  │  SHIPPING    │  │
│  │  (Downstream)    │  │ (Downstream)     │  │  CONTEXT     │  │
│  │                  │  │                  │  │ (Downstream) │  │
│  │ • Order          │  │ • Transaction    │  │              │  │
│  │ • LineItem       │  │ • Refund         │  │ • Shipment   │  │
│  │ • Fulfillment    │  │ • Settlement     │  │ • Carrier    │  │
│  └──────────────────┘  └──────────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Cart Context - Ubiquitous Language

| Term | Definition |
|------|------------|
| **Cart** | A temporary collection of items a customer intends to purchase |
| **CartItem** | A single product entry in the cart with quantity |
| **Condition** | A modifier that affects cart pricing (discount, tax, fee) |
| **Instance** | A named cart variant (default, wishlist, compare) |
| **Identifier** | The owner's unique ID (user ID or session ID) |
| **Checkout** | The process of converting a cart to an order |
| **Abandonment** | A cart left inactive beyond threshold time |

### Context Integration Patterns

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Application\Integration;

/**
 * Anti-Corruption Layer: Translates between Cart and Inventory contexts.
 */
final class InventoryIntegration
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
    ) {}
    
    /**
     * Check availability for cart items.
     * Translates Cart's CartItem to Inventory's StockQuery.
     */
    public function checkAvailability(Cart $cart): AvailabilityResult
    {
        $queries = [];
        
        foreach ($cart->getItems() as $item) {
            // Translation: CartItem -> StockQuery
            $queries[] = new StockQuery(
                sku: $item->getAttribute('sku'),
                quantity: $item->quantity()->getValue(),
                warehouseId: $cart->getMetadata('preferred_warehouse'),
            );
        }
        
        $stockResults = $this->inventoryService->checkStock($queries);
        
        // Translation: StockResult -> AvailabilityResult
        return $this->translateToAvailabilityResult($stockResults);
    }
    
    /**
     * Reserve inventory when checkout starts.
     */
    public function reserveForCheckout(Cart $cart, int $durationMinutes = 15): ReservationResult
    {
        $reservations = [];
        
        foreach ($cart->getItems() as $item) {
            $reservations[] = new ReservationRequest(
                sku: $item->getAttribute('sku'),
                quantity: $item->quantity()->getValue(),
                referenceType: 'cart',
                referenceId: $cart->getId()->toString(),
                expiresAt: now()->addMinutes($durationMinutes),
            );
        }
        
        return $this->inventoryService->reserve($reservations);
    }
}

/**
 * Anti-Corruption Layer: Translates between Cart and Payment contexts.
 */
final class PaymentIntegration
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}
    
    /**
     * Create payment intent from cart.
     * Translates Cart to Payment's CheckoutSession.
     */
    public function createPaymentIntent(Cart $cart, CustomerInfo $customer): PaymentIntent
    {
        // Translation: Cart -> CheckoutSession
        $session = new CheckoutSession(
            amount: $cart->calculateTotal()->getAmountInCents(),
            currency: $cart->getCurrency(),
            customerId: $customer->getId(),
            metadata: [
                'cart_id' => $cart->getId()->toString(),
                'cart_version' => $cart->getVersion(),
                'item_count' => count($cart->getItems()),
            ],
            lineItems: $this->translateLineItems($cart),
        );
        
        return $this->gateway->createIntent($session);
    }
    
    private function translateLineItems(Cart $cart): array
    {
        return array_map(
            fn ($item) => new PaymentLineItem(
                name: $item->getName(),
                unitAmount: $item->getPrice()->getAmountInCents(),
                quantity: $item->quantity()->getValue(),
            ),
            $cart->getItems()
        );
    }
}
```

---

## 3. Module Decomposition Strategy

### Gradual Migration Path

```
Phase 1: Extract Value Objects (Non-Breaking)
├── Create Domain/ValueObjects/ directory
├── Move CartId, Money, Quantity to value objects
├── Update existing code to use value objects
└── All tests pass

Phase 2: Extract Domain Events (Non-Breaking)
├── Create Domain/Events/ directory
├── Define domain event interfaces
├── Wrap existing Laravel events
└── All tests pass

Phase 3: Define Ports (Non-Breaking)
├── Create Domain/Contracts/ directory
├── Extract repository interfaces
├── Existing implementations satisfy interfaces
└── All tests pass

Phase 4: Implement Adapters (Non-Breaking)
├── Create Infrastructure/ directory
├── Move storage drivers to Infrastructure/Storage/
├── Register via service provider
└── All tests pass

Phase 5: Refactor Cart Aggregate (Breaking)
├── Create pure Domain/Aggregates/Cart.php
├── Move business logic from traits
├── Update all consumers
└── All tests pass (with updates)
```

### Backward Compatibility Layer

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Domain\Aggregates\Cart as DomainCart;

/**
 * Backward compatibility facade.
 * Proxies to new domain Cart while maintaining old API.
 * 
 * @deprecated Use AIArmada\Cart\Domain\Aggregates\Cart directly
 */
final class Cart implements CheckoutableInterface
{
    private DomainCart $domainCart;
    
    public function __construct(DomainCart $domainCart)
    {
        $this->domainCart = $domainCart;
    }
    
    // Old API methods delegate to new domain cart
    public function add(
        string|int|array $id,
        ?string $name = null,
        float|int|string|null $price = null,
        int $quantity = 1,
        array $attributes = [],
    ): CartItem|CartCollection {
        // Translate old API to new domain API
        $itemId = ItemId::fromString((string) $id);
        $money = Money::fromCents($this->normalizePrice($price));
        $qty = Quantity::of($quantity);
        
        $this->domainCart->addItem($itemId, $name, $money, $qty, $attributes);
        
        return $this->getItems()->get((string) $id);
    }
    
    // ... other backward-compatible methods
}
```

---

## 4. Dependency Inversion Patterns

### Service Provider Configuration

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Domain\Contracts\CartRepositoryInterface;
use AIArmada\Cart\Domain\Contracts\CartReadModelInterface;
use AIArmada\Cart\Domain\Contracts\EventPublisherInterface;
use AIArmada\Cart\Infrastructure\Persistence\EloquentCartRepository;
use AIArmada\Cart\Infrastructure\Persistence\RedisCartReadModel;
use AIArmada\Cart\Infrastructure\Messaging\LaravelEventPublisher;

final class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind ports to adapters
        $this->app->bind(
            CartRepositoryInterface::class,
            EloquentCartRepository::class
        );
        
        $this->app->bind(
            CartReadModelInterface::class,
            fn () => $this->resolveReadModel()
        );
        
        $this->app->bind(
            EventPublisherInterface::class,
            LaravelEventPublisher::class
        );
    }
    
    private function resolveReadModel(): CartReadModelInterface
    {
        $driver = config('cart.read_model.driver', 'eloquent');
        
        return match ($driver) {
            'redis' => $this->app->make(RedisCartReadModel::class),
            'elasticsearch' => $this->app->make(ElasticsearchCartReadModel::class),
            default => $this->app->make(EloquentCartReadModel::class),
        };
    }
}
```

---

## Summary: Structure Evolution Priority

| Change | Breaking | Complexity | Impact | Phase |
|--------|----------|------------|--------|-------|
| Value Objects | No | Low | Foundation | **Phase 1** |
| Domain Events | No | Low | Foundation | **Phase 1** |
| Port Interfaces | No | Low | Foundation | **Phase 1** |
| Infrastructure Layer | No | Medium | Organization | **Phase 2** |
| Cart Aggregate | Yes | High | Core | **Phase 3** |
| CQRS Commands | No | Medium | Scalability | **Phase 3** |
| Full Hexagonal | Yes | High | Maintainability | **Phase 4** |

---

**Next:** [05-performance-optimization.md](05-performance-optimization.md) - Multi-tier Caching, Lazy Evaluation
