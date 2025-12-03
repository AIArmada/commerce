# Cart Package Vision - Ecosystem Integration

> **Document:** 08-ecosystem-integration.md  
> **Series:** Cart Package Vision  
> **Focus:** Cross-Package Events, Commerce Pipeline, Package Orchestration

---

## Table of Contents

1. [Commerce Package Ecosystem](#1-commerce-package-ecosystem)
2. [Cross-Package Event Bus](#2-cross-package-event-bus)
3. [Integration Contracts](#3-integration-contracts)
4. [Commerce Pipeline](#4-commerce-pipeline)

---

## 1. Commerce Package Ecosystem

### Package Dependency Map

```
┌─────────────────────────────────────────────────────────────────┐
│                   COMMERCE PACKAGE ECOSYSTEM                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│                        ┌──────────────┐                         │
│                        │   CSUITE     │  (Meta-Package)         │
│                        │   (Optional) │                         │
│                        └──────┬───────┘                         │
│                               │                                  │
│         ┌─────────────────────┼─────────────────────┐           │
│         │                     │                     │           │
│         ▼                     ▼                     ▼           │
│  ┌─────────────┐      ┌─────────────┐      ┌─────────────┐     │
│  │    CART     │◄────►│   CASHIER   │◄────►│   VOUCHERS  │     │
│  │  (Core)     │      │  (Payments) │      │ (Discounts) │     │
│  └──────┬──────┘      └──────┬──────┘      └──────┬──────┘     │
│         │                    │                    │             │
│         │     ┌──────────────┴──────────────┐    │             │
│         │     │                              │    │             │
│         ▼     ▼                              ▼    ▼             │
│  ┌─────────────────┐               ┌─────────────────┐         │
│  │   INVENTORY     │               │   AFFILIATES    │         │
│  │  (Stock Mgmt)   │               │  (Referrals)    │         │
│  └─────────────────┘               └─────────────────┘         │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │      CHIP       │  │      JNT        │  │     STOCK       │ │
│  │ (Payment GW)    │  │   (Shipping)    │  │  (Warehouse)    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                  FILAMENT PACKAGES                       │   │
│  │  filament-cart, filament-vouchers, filament-inventory    │   │
│  │  filament-chip, filament-jnt, filament-affiliates        │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                  COMMERCE-SUPPORT                        │   │
│  │       (Shared utilities, Money, base classes)            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

LEGEND:
  ◄──► = Bidirectional integration
  ───► = Dependency direction
```

### Package Capabilities Matrix

| Package | Cart Events | Provides to Cart | Receives from Cart |
|---------|-------------|------------------|-------------------|
| Cashier | ✅ | Payment status | Checkout totals |
| Vouchers | ✅ | Discount conditions | Cart subtotals |
| Inventory | ✅ | Stock validation | Item quantities |
| Affiliates | ✅ | Commission conditions | Cart conversions |
| Chip | ✅ | Payment processing | Payment requests |
| JNT | ✅ | Shipping rates | Shipping items |
| Stock | ✅ | Availability | Reserved items |

---

## 2. Cross-Package Event Bus

### Vision Statement

Create a **unified event bus** that enables loose coupling between commerce packages while maintaining strong integration.

### Event Bus Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    COMMERCE EVENT BUS                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌───────────────────────────────────────────────────────┐    │
│   │                  EVENT DISPATCHER                      │    │
│   │      (Laravel Events + Commerce Extensions)            │    │
│   └───────────────────────────────────────────────────────┘    │
│                              │                                  │
│         ┌────────────────────┼────────────────────┐            │
│         │                    │                    │            │
│         ▼                    ▼                    ▼            │
│   ┌───────────┐        ┌───────────┐        ┌───────────┐     │
│   │ SYNC      │        │ ASYNC     │        │ BROADCAST │     │
│   │ Listeners │        │ Queue     │        │ WebSocket │     │
│   └───────────┘        └───────────┘        └───────────┘     │
│                                                                 │
│   EVENT TYPES:                                                  │
│   • CartEvents      → Vouchers, Inventory, Affiliates          │
│   • PaymentEvents   → Cart, Inventory, Affiliates              │
│   • InventoryEvents → Cart (stock updates)                     │
│   • ShippingEvents  → Cart (rate updates)                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Commerce Event Contracts

```php
<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Events;

/**
 * Base contract for all commerce events.
 */
interface CommerceEventInterface
{
    /**
     * Get the aggregate ID this event relates to.
     */
    public function getAggregateId(): string;
    
    /**
     * Get the aggregate type (cart, order, payment, etc.).
     */
    public function getAggregateType(): string;
    
    /**
     * Get event metadata for correlation/causation.
     */
    public function getMetadata(): array;
    
    /**
     * Get the event payload.
     */
    public function getPayload(): array;
    
    /**
     * Get event timestamp.
     */
    public function getOccurredAt(): \DateTimeImmutable;
}

/**
 * Contract for cart-specific events.
 */
interface CartEventInterface extends CommerceEventInterface
{
    public function getCartId(): string;
    public function getCartIdentifier(): string;
    public function getCartInstance(): string;
}

/**
 * Contract for events that affect cart totals.
 */
interface CartTotalAffectingEventInterface extends CartEventInterface
{
    /**
     * Should the cart recalculate totals after this event?
     */
    public function requiresRecalculation(): bool;
}
```

### Cart Events for Cross-Package Integration

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;

/**
 * Fired when cart is ready for checkout.
 * Triggers: Voucher validation, Inventory reservation, Affiliate tracking
 */
final readonly class CartCheckoutInitiated implements CartEventInterface
{
    public function __construct(
        public string $cartId,
        public string $identifier,
        public string $instance,
        public array $items,
        public int $subtotalCents,
        public int $totalCents,
        public array $conditions,
        public array $metadata,
        public \DateTimeImmutable $occurredAt,
    ) {}
    
    public function getAggregateId(): string
    {
        return $this->cartId;
    }
    
    public function getAggregateType(): string
    {
        return 'cart';
    }
    
    public function getPayload(): array
    {
        return [
            'cart_id' => $this->cartId,
            'identifier' => $this->identifier,
            'instance' => $this->instance,
            'items' => $this->items,
            'subtotal_cents' => $this->subtotalCents,
            'total_cents' => $this->totalCents,
            'conditions' => $this->conditions,
            'metadata' => $this->metadata,
        ];
    }
    
    // ... other interface methods
}

/**
 * Fired when cart checkout completes successfully.
 * Triggers: Inventory decrement, Affiliate conversion, Analytics
 */
final readonly class CartCheckoutCompleted implements CartEventInterface
{
    public function __construct(
        public string $cartId,
        public string $orderId,
        public string $paymentId,
        public array $items,
        public int $totalCents,
        public ?string $affiliateCode,
        public \DateTimeImmutable $occurredAt,
    ) {}
    
    // ... interface implementation
}

/**
 * Fired when item is added to cart.
 * Triggers: Inventory soft-reserve, Dynamic pricing, Recommendations
 */
final readonly class CartItemAdded implements CartEventInterface
{
    public function __construct(
        public string $cartId,
        public string $itemId,
        public string $productId,
        public int $quantity,
        public int $priceCents,
        public \DateTimeImmutable $occurredAt,
    ) {}
    
    // ... interface implementation
}
```

### Event Listeners in Other Packages

```php
<?php

// In packages/vouchers/src/Listeners/ValidateVoucherOnCheckout.php
namespace AIArmada\Vouchers\Listeners;

use AIArmada\Cart\Events\CartCheckoutInitiated;

final class ValidateVoucherOnCheckout
{
    public function handle(CartCheckoutInitiated $event): void
    {
        $voucherConditions = collect($event->conditions)
            ->filter(fn($c) => $c['type'] === 'voucher');
        
        foreach ($voucherConditions as $condition) {
            $voucher = Voucher::findByCode($condition['name']);
            
            if (!$voucher || !$voucher->isValidFor($event->cartId, $event->totalCents)) {
                throw new VoucherValidationException(
                    "Voucher {$condition['name']} is no longer valid"
                );
            }
        }
    }
}

// In packages/inventory/src/Listeners/ReserveStockOnCheckout.php
namespace AIArmada\Inventory\Listeners;

use AIArmada\Cart\Events\CartCheckoutInitiated;

final class ReserveStockOnCheckout
{
    public function handle(CartCheckoutInitiated $event): void
    {
        foreach ($event->items as $item) {
            $reservation = StockReservation::create([
                'product_id' => $item['associated_model']['id'],
                'quantity' => $item['quantity'],
                'cart_id' => $event->cartId,
                'expires_at' => now()->addMinutes(15),
            ]);
            
            if (!$reservation) {
                throw new InsufficientStockException(
                    "Cannot reserve {$item['quantity']} of {$item['name']}"
                );
            }
        }
    }
}

// In packages/affiliates/src/Listeners/TrackAffiliateConversion.php
namespace AIArmada\Affiliates\Listeners;

use AIArmada\Cart\Events\CartCheckoutCompleted;

final class TrackAffiliateConversion
{
    public function handle(CartCheckoutCompleted $event): void
    {
        if (!$event->affiliateCode) {
            return;
        }
        
        AffiliateConversion::create([
            'affiliate_code' => $event->affiliateCode,
            'order_id' => $event->orderId,
            'cart_id' => $event->cartId,
            'total_cents' => $event->totalCents,
            'items_count' => count($event->items),
        ]);
    }
}
```

---

## 3. Integration Contracts

### Condition Provider Interface

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

/**
 * Interface for packages that provide cart conditions.
 * Implemented by: Vouchers, Affiliates, Shipping providers
 */
interface ConditionProviderInterface
{
    /**
     * Get conditions applicable to the cart.
     * 
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array;
    
    /**
     * Validate a condition is still applicable.
     */
    public function validate(CartCondition $condition, Cart $cart): bool;
    
    /**
     * Get the condition type identifier.
     */
    public function getType(): string;
    
    /**
     * Get the priority for condition application.
     */
    public function getPriority(): int;
}
```

### Voucher Integration

```php
<?php

// In packages/vouchers/src/Cart/VoucherConditionProvider.php
namespace AIArmada\Vouchers\Cart;

use AIArmada\Cart\Contracts\ConditionProviderInterface;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;

final class VoucherConditionProvider implements ConditionProviderInterface
{
    public function getConditionsFor(Cart $cart): array
    {
        $conditions = [];
        $voucherCodes = $cart->getMetadata('voucher_codes', []);
        
        foreach ($voucherCodes as $code) {
            $voucher = Voucher::findByCode($code);
            
            if ($voucher && $voucher->isValidForCart($cart)) {
                $conditions[] = new CartCondition([
                    'name' => $voucher->code,
                    'type' => 'voucher',
                    'value' => $voucher->getDiscountValue(),
                    'target' => $voucher->getTarget()->value,
                    'attributes' => [
                        'voucher_id' => $voucher->id,
                        'description' => $voucher->description,
                        'min_purchase' => $voucher->min_purchase_cents,
                    ],
                ]);
            }
        }
        
        return $conditions;
    }
    
    public function validate(CartCondition $condition, Cart $cart): bool
    {
        $voucher = Voucher::findByCode($condition->getName());
        
        return $voucher 
            && $voucher->isValidForCart($cart)
            && $voucher->hasUsagesRemaining();
    }
    
    public function getType(): string
    {
        return 'voucher';
    }
    
    public function getPriority(): int
    {
        return 100; // Apply after shipping, before tax
    }
}
```

### Inventory Integration

```php
<?php

// In packages/inventory/src/Cart/InventoryValidator.php
namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartValidatorInterface;

final class InventoryValidator implements CartValidatorInterface
{
    /**
     * Validate cart items against inventory.
     */
    public function validate(Cart $cart): ValidationResult
    {
        $errors = [];
        
        foreach ($cart->items() as $item) {
            $product = $item->getAssociatedModel();
            
            if (!$product) {
                continue;
            }
            
            $available = Inventory::getAvailableQuantity($product->id);
            
            if ($item->quantity > $available) {
                $errors[] = new ValidationError(
                    field: "items.{$item->id}.quantity",
                    message: "Only {$available} available for {$item->name}",
                    code: 'insufficient_stock',
                    context: [
                        'requested' => $item->quantity,
                        'available' => $available,
                        'product_id' => $product->id,
                    ]
                );
            }
        }
        
        return new ValidationResult($errors);
    }
    
    /**
     * Priority for validation order.
     */
    public function getPriority(): int
    {
        return 10; // Run early
    }
}
```

### Shipping Integration

```php
<?php

// In packages/jnt/src/Cart/JNTShippingProvider.php
namespace AIArmada\JNT\Cart;

use AIArmada\Cart\Contracts\ShippingProviderInterface;
use AIArmada\Cart\Cart;

final class JNTShippingProvider implements ShippingProviderInterface
{
    /**
     * Get available shipping rates for cart.
     */
    public function getRates(Cart $cart, Address $destination): array
    {
        $items = $cart->items()->map(function ($item) {
            return [
                'weight' => $item->getAttribute('weight', 0),
                'dimensions' => $item->getAttribute('dimensions', []),
                'quantity' => $item->quantity,
            ];
        });
        
        $rates = $this->client->calculateRates(
            origin: config('jnt.origin_address'),
            destination: $destination,
            items: $items->toArray()
        );
        
        return array_map(function ($rate) {
            return new ShippingRate(
                provider: 'jnt',
                service: $rate['service_code'],
                name: $rate['service_name'],
                priceCents: $rate['price_cents'],
                estimatedDays: $rate['estimated_days'],
                metadata: $rate
            );
        }, $rates);
    }
    
    /**
     * Create shipping condition for cart.
     */
    public function createCondition(ShippingRate $rate): CartCondition
    {
        return new CartCondition([
            'name' => "shipping_{$rate->provider}_{$rate->service}",
            'type' => 'shipping',
            'value' => $rate->priceCents,
            'target' => ConditionTarget::SHIPPING->value,
            'attributes' => [
                'provider' => $rate->provider,
                'service' => $rate->service,
                'estimated_days' => $rate->estimatedDays,
            ],
        ]);
    }
}
```

---

## 4. Commerce Pipeline

### Pipeline Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    COMMERCE CHECKOUT PIPELINE                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  STAGE 1: CART VALIDATION                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Stock availability check (Inventory)                   │   │
│  │ • Price verification (Cart)                              │   │
│  │ • Condition validation (Vouchers)                        │   │
│  │ • Business rules (Cart)                                  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  STAGE 2: TOTALS CALCULATION                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Apply item conditions                                  │   │
│  │ • Apply cart conditions (Vouchers)                       │   │
│  │ • Calculate shipping (JNT)                               │   │
│  │ • Calculate tax                                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  STAGE 3: RESERVATION                                           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Reserve stock (Inventory)                              │   │
│  │ • Lock voucher usage (Vouchers)                          │   │
│  │ • Create payment intent (Chip/Cashier)                   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  STAGE 4: PAYMENT                                               │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Process payment (Chip/Cashier)                         │   │
│  │ • Handle 3DS/verification                                │   │
│  │ • Capture funds                                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  STAGE 5: FULFILLMENT                                           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ • Decrement stock (Inventory)                            │   │
│  │ • Consume voucher (Vouchers)                             │   │
│  │ • Track conversion (Affiliates)                          │   │
│  │ • Create order (External)                                │   │
│  │ • Trigger notifications                                  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Pipeline Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout;

/**
 * Commerce checkout pipeline orchestrator.
 */
final class CheckoutPipeline
{
    /** @var array<PipelineStageInterface> */
    private array $stages;
    
    public function __construct(
        private CheckoutContext $context,
        private PipelineEventDispatcher $dispatcher,
        private CheckoutSagaManager $sagaManager,
    ) {
        $this->stages = $this->buildStages();
    }
    
    /**
     * Execute checkout pipeline.
     */
    public function execute(Cart $cart): CheckoutResult
    {
        $saga = $this->sagaManager->begin($cart);
        
        try {
            $this->dispatcher->dispatch(new CheckoutStarted($cart));
            
            foreach ($this->stages as $stage) {
                $this->dispatcher->dispatch(new StageStarted($stage, $cart));
                
                $result = $stage->execute($cart, $this->context);
                
                if ($result->failed()) {
                    return $this->handleFailure($cart, $stage, $result, $saga);
                }
                
                $saga->checkpoint($stage->getName(), $result);
                
                $this->dispatcher->dispatch(new StageCompleted($stage, $cart, $result));
            }
            
            $saga->complete();
            $this->dispatcher->dispatch(new CheckoutCompleted($cart, $saga->getResult()));
            
            return CheckoutResult::success($saga->getResult());
            
        } catch (\Throwable $e) {
            $saga->fail($e);
            $this->dispatcher->dispatch(new CheckoutFailed($cart, $e));
            
            return CheckoutResult::failure($e);
        }
    }
    
    /**
     * Build pipeline stages based on installed packages.
     * 
     * @return array<PipelineStageInterface>
     */
    private function buildStages(): array
    {
        $stages = [];
        
        // Stage 1: Validation (always)
        $stages[] = new ValidationStage([
            new CartValidator(),
            new PriceValidator(),
        ]);
        
        // Add inventory validator if package installed
        if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            $stages[0]->addValidator(app(InventoryValidator::class));
        }
        
        // Add voucher validator if package installed
        if (class_exists(\AIArmada\Vouchers\VouchersServiceProvider::class)) {
            $stages[0]->addValidator(app(VoucherValidator::class));
        }
        
        // Stage 2: Calculation (always)
        $stages[] = new CalculationStage();
        
        // Stage 3: Reservation
        $reservationStage = new ReservationStage();
        
        if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            $reservationStage->addReserver(app(StockReserver::class));
        }
        
        if (class_exists(\AIArmada\Vouchers\VouchersServiceProvider::class)) {
            $reservationStage->addReserver(app(VoucherLocker::class));
        }
        
        $stages[] = $reservationStage;
        
        // Stage 4: Payment
        if (class_exists(\AIArmada\Cashier\CashierServiceProvider::class)) {
            $stages[] = new PaymentStage(app(PaymentProcessor::class));
        } elseif (class_exists(\AIArmada\Chip\ChipServiceProvider::class)) {
            $stages[] = new PaymentStage(app(ChipPaymentProcessor::class));
        }
        
        // Stage 5: Fulfillment
        $fulfillmentStage = new FulfillmentStage();
        
        if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            $fulfillmentStage->addHandler(app(StockDecrementer::class));
        }
        
        if (class_exists(\AIArmada\Vouchers\VouchersServiceProvider::class)) {
            $fulfillmentStage->addHandler(app(VoucherConsumer::class));
        }
        
        if (class_exists(\AIArmada\Affiliates\AffiliatesServiceProvider::class)) {
            $fulfillmentStage->addHandler(app(AffiliateTracker::class));
        }
        
        $stages[] = $fulfillmentStage;
        
        return $stages;
    }
    
    private function handleFailure(
        Cart $cart,
        PipelineStageInterface $stage,
        StageResult $result,
        CheckoutSaga $saga
    ): CheckoutResult {
        // Compensate completed stages in reverse order
        $saga->compensate();
        
        return CheckoutResult::failure(
            new CheckoutException(
                "Checkout failed at stage {$stage->getName()}: {$result->getError()}",
                $result->getErrors()
            )
        );
    }
}
```

### Saga Pattern for Rollback

```php
<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout;

/**
 * Saga manager for distributed transaction rollback.
 */
final class CheckoutSaga
{
    /** @var array<string, SagaCheckpoint> */
    private array $checkpoints = [];
    
    private SagaStatus $status = SagaStatus::InProgress;
    
    public function __construct(
        private string $sagaId,
        private Cart $cart,
        private SagaStorageInterface $storage,
    ) {}
    
    public function checkpoint(string $stageName, StageResult $result): void
    {
        $checkpoint = new SagaCheckpoint(
            stageName: $stageName,
            result: $result,
            compensationData: $result->getCompensationData(),
            timestamp: now(),
        );
        
        $this->checkpoints[$stageName] = $checkpoint;
        $this->storage->saveCheckpoint($this->sagaId, $checkpoint);
    }
    
    /**
     * Compensate (rollback) all completed stages.
     */
    public function compensate(): void
    {
        $this->status = SagaStatus::Compensating;
        
        // Process in reverse order
        $stages = array_reverse(array_keys($this->checkpoints));
        
        foreach ($stages as $stageName) {
            $checkpoint = $this->checkpoints[$stageName];
            
            try {
                $compensator = $this->getCompensator($stageName);
                $compensator->compensate($checkpoint->compensationData);
                
            } catch (\Throwable $e) {
                // Log compensation failure but continue
                logger()->error("Saga compensation failed for {$stageName}", [
                    'saga_id' => $this->sagaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->status = SagaStatus::Compensated;
        $this->storage->updateStatus($this->sagaId, $this->status);
    }
    
    private function getCompensator(string $stageName): CompensatorInterface
    {
        return match($stageName) {
            'reservation' => app(ReservationCompensator::class),
            'payment' => app(PaymentCompensator::class),
            'fulfillment' => app(FulfillmentCompensator::class),
            default => new NullCompensator(),
        };
    }
}
```

---

## Summary: Ecosystem Integration Priorities

| Integration | Complexity | Impact | Priority |
|-------------|------------|--------|----------|
| Event Contracts | Low | High | **P0** |
| Voucher Integration | Medium | High | **P0** |
| Inventory Integration | Medium | High | **P0** |
| Checkout Pipeline | High | Critical | **P1** |
| Saga Rollback | High | High | **P1** |
| Shipping Integration | Medium | Medium | **P2** |
| Affiliate Tracking | Low | Medium | **P2** |

---

**Next:** [09-filament-enhancements.md](09-filament-enhancements.md) - Admin Dashboard, Real-time Monitoring
