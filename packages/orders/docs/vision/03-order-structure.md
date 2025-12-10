# Order Structure

> **Document:** 03 of 08  
> **Package:** `aiarmada/orders`  
> **Status:** Vision

---

## Overview

This document details the data structure of orders, including the Order model, OrderItem, OrderAddress, and related models.

---

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         ORDER ENTITY RELATIONSHIPS                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                              ┌─────────────────┐                             │
│                              │     Customer    │                             │
│                              │   (customers)   │                             │
│                              └────────┬────────┘                             │
│                                       │ 1:N                                  │
│                                       ▼                                      │
│ ┌────────────────┐            ┌─────────────────┐         ┌────────────────┐│
│ │  OrderAddress  │ N:1        │      ORDER      │    1:N  │   OrderItem    ││
│ │  (addresses)   │◀───────────│   (orders)      │────────▶│    (items)     ││
│ └────────────────┘            └────────┬────────┘         └────────────────┘│
│                                        │                                     │
│        ┌──────────────┬────────────────┼────────────────┬──────────────┐    │
│        │              │                │                │              │    │
│        ▼              ▼                ▼                ▼              ▼    │
│ ┌────────────┐ ┌────────────┐ ┌─────────────┐ ┌────────────┐ ┌────────────┐ │
│ │OrderPayment│ │ OrderNote  │ │OrderHistory │ │ Shipment   │ │OrderRefund │ │
│ │ (payments) │ │  (notes)   │ │  (history)  │ │(shipments) │ │ (refunds)  │ │
│ └────────────┘ └────────────┘ └─────────────┘ └────────────┘ └────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Order Model

```php
namespace AIArmada\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;
use AIArmada\Orders\States\OrderStatus;
use AIArmada\CommerceSupport\Traits\HasMoney;

class Order extends Model
{
    use HasStates;
    use HasMoney;

    protected $fillable = [
        'order_number',
        'customer_id',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'notes',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'canceled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'integer',
        'discount_total' => 'integer',
        'shipping_total' => 'integer',
        'tax_total' => 'integer',
        'grand_total' => 'integer',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo;
    public function items(): HasMany;
    public function addresses(): HasMany;
    public function billingAddress(): HasOne;
    public function shippingAddress(): HasOne;
    public function payments(): HasMany;
    public function refunds(): HasMany;
    public function notes(): HasMany;
    public function history(): HasMany;
    public function shipments(): HasMany;

    // Scopes
    public function scopePaid($query);
    public function scopeUnpaid($query);
    public function scopeFulfilled($query);
    public function scopeUnfulfilled($query);

    // Helpers
    public function isPaid(): bool;
    public function isFullyRefunded(): bool;
    public function getRefundableAmount(): Money;
}
```

---

## OrderItem Model

```php
namespace AIArmada\Orders\Models;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'purchasable_type',    // Product, Variant, Subscription
        'purchasable_id',
        'sku',
        'name',
        'options',              // JSON: size: L, color: Red
        'quantity',
        'unit_price',           // Price per unit at time of order
        'unit_discount',        // Discount per unit
        'unit_tax',             // Tax per unit
        'line_total',           // (unit_price - unit_discount + unit_tax) * quantity
        'tax_class',            // Tax class at time of order
        'weight',               // For shipping calculation
        'is_digital',           // No shipping required
        'fulfilled_quantity',   // How many have been shipped
    ];

    protected $casts = [
        'options' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'unit_discount' => 'integer',
        'unit_tax' => 'integer',
        'line_total' => 'integer',
        'weight' => 'decimal:2',
        'is_digital' => 'boolean',
        'fulfilled_quantity' => 'integer',
    ];

    // Relationships
    public function order(): BelongsTo;
    public function purchasable(): MorphTo;

    // Helpers
    public function isFullyFulfilled(): bool;
    public function getUnfulfilledQuantity(): int;
}
```

---

## OrderAddress Model

```php
namespace AIArmada\Orders\Models;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id',
        'type',                 // AddressType::Billing, Shipping
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'phone',
        'email',
    ];

    protected $casts = [
        'type' => AddressType::class,
    ];

    // Relationships
    public function order(): BelongsTo;

    // Helpers
    public function getFullName(): string;
    public function getFormattedAddress(): string;
}
```

---

## OrderPayment Model

```php
namespace AIArmada\Orders\Models;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',              // stripe, chip, manual
        'transaction_id',       // Gateway's transaction ID
        'amount',
        'currency',
        'status',               // pending, completed, failed, refunded
        'payment_method',       // card, fpx, ewallet
        'card_last_four',       // For display
        'card_brand',           // visa, mastercard
        'metadata',             // JSON: Stripe PaymentIntent, etc.
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function order(): BelongsTo;
}
```

---

## OrderRefund Model

```php
namespace AIArmada\Orders\Models;

class OrderRefund extends Model
{
    protected $fillable = [
        'order_id',
        'payment_id',           // Which payment is being refunded
        'amount',
        'reason',
        'notes',
        'gateway_refund_id',    // Gateway's refund reference
        'status',               // pending, completed, failed
        'refunded_by',          // User ID who processed
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'refunded_at' => 'datetime',
    ];

    // Relationships
    public function order(): BelongsTo;
    public function payment(): BelongsTo;
    public function refundedBy(): BelongsTo;
}
```

---

## OrderNote Model

```php
namespace AIArmada\Orders\Models;

class OrderNote extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',              // Who added the note
        'content',
        'is_customer_visible',  // Show to customer?
        'type',                 // NoteType::Internal, Customer, System
    ];

    protected $casts = [
        'is_customer_visible' => 'boolean',
        'type' => NoteType::class,
    ];

    // Relationships
    public function order(): BelongsTo;
    public function user(): BelongsTo;
}
```

---

## OrderHistory Model

Timeline of all order events.

```php
namespace AIArmada\Orders\Models;

class OrderHistory extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',              // Who triggered (null for system)
        'event',                // OrderEvent enum
        'description',          // Human-readable description
        'old_values',           // JSON: previous state
        'new_values',           // JSON: new state
        'metadata',             // JSON: additional context
    ];

    protected $casts = [
        'event' => OrderEvent::class,
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    // Relationships
    public function order(): BelongsTo;
    public function user(): BelongsTo;
}
```

---

## Order Number Generation

```php
namespace AIArmada\Orders\Services;

class OrderNumberGenerator
{
    /**
     * Generate unique order number
     * Format: {PREFIX}{YYMM}{SEQUENCE}
     * Example: ORD2412-00001
     */
    public function generate(): string
    {
        $prefix = config('orders.number_prefix', 'ORD');
        $date = now()->format('ym');
        $sequence = $this->getNextSequence();
        
        return sprintf('%s%s-%05d', $prefix, $date, $sequence);
    }

    protected function getNextSequence(): int
    {
        return DB::table('order_sequences')
            ->where('month', now()->format('Ym'))
            ->lockForUpdate()
            ->increment('sequence') ?? 1;
    }
}
```

---

## Cart to Order Conversion

```php
namespace AIArmada\Orders\Services;

class OrderFactory
{
    public function createFromCart(
        Cart $cart,
        Address $billingAddress,
        Address $shippingAddress,
        ?Customer $customer = null
    ): Order {
        return DB::transaction(function () use ($cart, $billingAddress, $shippingAddress, $customer) {
            // Create order
            $order = Order::create([
                'order_number' => $this->numberGenerator->generate(),
                'customer_id' => $customer?->id,
                'currency' => $cart->getCurrency(),
                'subtotal' => $cart->getSubtotal(),
                'discount_total' => $cart->getDiscountTotal(),
                'shipping_total' => $cart->getShippingTotal(),
                'tax_total' => $cart->getTaxTotal(),
                'grand_total' => $cart->getGrandTotal(),
            ]);

            // Add items (snapshot from cart)
            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'purchasable_type' => get_class($cartItem->purchasable),
                    'purchasable_id' => $cartItem->purchasable->id,
                    'sku' => $cartItem->purchasable->sku,
                    'name' => $cartItem->purchasable->name,
                    'options' => $cartItem->options,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'unit_discount' => $cartItem->unit_discount,
                    'unit_tax' => $cartItem->unit_tax,
                    'line_total' => $cartItem->line_total,
                    'tax_class' => $cartItem->tax_class,
                    'weight' => $cartItem->weight,
                    'is_digital' => $cartItem->is_digital,
                ]);
            }

            // Add addresses (snapshot)
            $order->addresses()->createMany([
                $this->snapshotAddress($billingAddress, AddressType::Billing),
                $this->snapshotAddress($shippingAddress, AddressType::Shipping),
            ]);

            // Record history
            $order->history()->create([
                'event' => OrderEvent::Created,
                'description' => 'Order created from cart',
            ]);

            // Dispatch event
            event(new OrderCreated($order));

            return $order;
        });
    }
}
```

---

## Navigation

**Previous:** [02-state-machine.md](02-state-machine.md)  
**Next:** [04-payment-integration.md](04-payment-integration.md)
