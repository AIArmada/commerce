# Gift Card System

> **Document:** 04-gift-card-system.md  
> **Status:** Vision  
> **Priority:** P1

---

## Overview

Gift cards represent a **stored value** voucher type that differs fundamentally from discount vouchers:

| Aspect | Discount Voucher | Gift Card |
|--------|------------------|-----------|
| Value Type | Reduction rule | Stored balance |
| Application | Single use calculation | Partial balance deduction |
| Tracking | Usage count | Balance ledger |
| Transferability | Usually no | Yes (gifting) |
| Refillability | No | Yes (top-up) |

---

## Vision: Complete Gift Card Infrastructure

### 4.1 Gift Card Types

```php
enum GiftCardType: string
{
    case Standard = 'standard';        // Fixed denomination
    case OpenValue = 'open_value';     // Buyer chooses amount
    case Promotional = 'promotional';  // Issued by merchant (no purchase)
    case Reward = 'reward';            // Earned through loyalty
    case Corporate = 'corporate';      // B2B bulk purchase
}
```

### 4.2 Gift Card Lifecycle

```
┌─────────────────────────────────────────────────────────────────────┐
│                     GIFT CARD LIFECYCLE                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  CREATION                                                            │
│  ├── Purchase (customer buys for self/other)                        │
│  ├── Issue (merchant creates promotional)                           │
│  └── Earn (loyalty/reward program)                                  │
│           │                                                          │
│           ▼                                                          │
│  ACTIVATION                                                          │
│  ├── Immediate (upon purchase confirmation)                         │
│  ├── Delayed (future activation date)                               │
│  └── Manual (requires merchant activation)                          │
│           │                                                          │
│           ▼                                                          │
│  USAGE                                                               │
│  ├── Full redemption (balance = cart total)                        │
│  ├── Partial redemption (balance > cart total, remainder stays)    │
│  ├── Combined payment (balance < cart total, split payment)        │
│  └── Multi-card (stack multiple gift cards)                        │
│           │                                                          │
│           ▼                                                          │
│  MAINTENANCE                                                         │
│  ├── Balance check                                                   │
│  ├── Top-up (add value)                                             │
│  ├── Transfer (gift to another user)                                │
│  └── Merge (combine balances)                                       │
│           │                                                          │
│           ▼                                                          │
│  EXPIRATION                                                          │
│  ├── Hard expiry (balance forfeited)                                │
│  ├── Soft expiry (inactivity fee deductions)                        │
│  └── No expiry (balance never expires)                              │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Gift Cards Table

```php
Schema::create(config('vouchers.tables.gift_cards', 'gift_cards'), function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Identification
    $table->string('code', 32)->unique();
    $table->string('pin', 8)->nullable(); // Optional security PIN
    
    // Type & Configuration
    $table->string('type')->default('standard');
    $table->string('currency', 3)->default('MYR');
    
    // Balance (stored in cents)
    $table->bigInteger('initial_balance');
    $table->bigInteger('current_balance');
    
    // Status
    $table->string('status')->default('inactive');
    $table->timestamp('activated_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('last_used_at')->nullable();
    
    // Ownership
    $table->nullableUuidMorphs('purchaser'); // Who bought it
    $table->nullableUuidMorphs('recipient'); // Who owns it now
    
    // Multi-tenancy
    $table->nullableUuidMorphs('owner');
    
    // Metadata
    $table->jsonb('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['status', 'expires_at']);
    $table->index(['recipient_type', 'recipient_id']);
});
```

### Gift Card Transactions Table

```php
Schema::create(config('vouchers.tables.gift_card_transactions', 'gift_card_transactions'), function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('gift_card_id');
    
    // Transaction Type
    $table->string('type'); // 'issue', 'activate', 'redeem', 'topup', 'refund', 'expire', 'transfer', 'fee'
    
    // Amount (positive for credit, negative for debit)
    $table->bigInteger('amount');
    $table->bigInteger('balance_before');
    $table->bigInteger('balance_after');
    
    // Reference
    $table->nullableUuidMorphs('reference'); // Order, refund, etc.
    $table->string('description')->nullable();
    
    // Actor
    $table->nullableUuidMorphs('actor'); // User who initiated
    
    $table->jsonb('metadata')->nullable();
    $table->timestamps();
    
    // Indexes
    $table->index(['gift_card_id', 'created_at']);
    $table->index(['reference_type', 'reference_id']);
});
```

---

## Cart Integration

### Balance Deduction Operator

The cart package needs a new operator for balance-based deductions:

```php
// In CartCondition
private function applyBalanceDeduction(int $baseValue): int
{
    $balanceSource = $this->attributes['balance_source'] ?? null;
    
    if (!$balanceSource instanceof BalanceSourceInterface) {
        return $baseValue;
    }
    
    $availableBalance = $balanceSource->getAvailableBalance();
    $deduction = min($availableBalance, $baseValue);
    
    // Store intended deduction for later commitment
    $this->attributes['pending_deduction'] = $deduction;
    
    return $baseValue - $deduction;
}
```

### Gift Card Condition

```php
class GiftCardCondition extends VoucherCondition
{
    public function __construct(
        private GiftCard $giftCard,
        int $order = 0
    ) {
        parent::__construct(
            voucher: $this->createVoucherData(),
            order: $order,
            dynamic: true
        );
    }
    
    public function toCartCondition(): CartCondition
    {
        return new CartCondition(
            name: "gift_card_{$this->giftCard->code}",
            type: 'gift_card',
            target: Target::cart()
                ->phase(ConditionPhase::GRAND_TOTAL) // Apply after discounts
                ->build(),
            value: "~{$this->giftCard->current_balance}", // Balance operator
            attributes: [
                'gift_card_id' => $this->giftCard->id,
                'gift_card_code' => $this->giftCard->code,
                'available_balance' => $this->giftCard->current_balance,
                'balance_source' => $this->giftCard,
            ],
            order: $this->order,
            rules: [[$this, 'validateGiftCard']]
        );
    }
    
    public function validateGiftCard(Cart $cart, ?CartItem $item = null): bool
    {
        return $this->giftCard->isActive() 
            && $this->giftCard->current_balance > 0
            && !$this->giftCard->isExpired();
    }
}
```

### Multi-Card Support

```php
// Cart methods via InteractsWithGiftCards trait
trait InteractsWithGiftCards
{
    public function applyGiftCard(string $code, ?string $pin = null): static
    {
        $giftCard = GiftCard::findByCode($code);
        
        if ($pin !== null && !$giftCard->verifyPin($pin)) {
            throw new InvalidGiftCardPinException($code);
        }
        
        $condition = new GiftCardCondition($giftCard);
        $this->addCondition($condition->toCartCondition());
        
        return $this;
    }
    
    public function getAppliedGiftCards(): Collection
    {
        return $this->getConditions()
            ->filter(fn ($c) => $c->getType() === 'gift_card');
    }
    
    public function getGiftCardTotal(): int
    {
        return $this->getAppliedGiftCards()
            ->sum(fn ($c) => $c->getAttribute('pending_deduction', 0));
    }
    
    public function getRemainingBalance(): int
    {
        $total = $this->getRawTotalWithoutConditions();
        $giftCardTotal = $this->getGiftCardTotal();
        
        return max(0, $total - $giftCardTotal);
    }
}
```

---

## Checkout Flow

### Balance Commitment

```php
class GiftCardPaymentHandler
{
    public function processPayment(Order $order, Cart $cart): PaymentResult
    {
        $giftCardConditions = $cart->getAppliedGiftCards();
        
        foreach ($giftCardConditions as $condition) {
            $giftCardId = $condition->getAttribute('gift_card_id');
            $deduction = $condition->getAttribute('pending_deduction');
            
            $giftCard = GiftCard::find($giftCardId);
            
            // Create transaction record
            $giftCard->debit(
                amount: $deduction,
                type: 'redeem',
                reference: $order,
                description: "Order #{$order->number}"
            );
        }
        
        return new PaymentResult(
            success: true,
            amount: $cart->getGiftCardTotal(),
            method: 'gift_card'
        );
    }
}
```

### Refund Handling

```php
class GiftCardRefundHandler
{
    public function processRefund(Order $order, int $amount): void
    {
        // Find gift card transactions for this order
        $transactions = GiftCardTransaction::query()
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', 'redeem')
            ->get();
        
        $remaining = $amount;
        
        foreach ($transactions as $transaction) {
            $refundAmount = min(abs($transaction->amount), $remaining);
            
            $transaction->giftCard->credit(
                amount: $refundAmount,
                type: 'refund',
                reference: $order,
                description: "Refund for Order #{$order->number}"
            );
            
            $remaining -= $refundAmount;
            
            if ($remaining <= 0) {
                break;
            }
        }
    }
}
```

---

## Gift Card Service API

```php
class GiftCardService
{
    // Creation
    public function issue(array $data): GiftCard;
    public function purchase(int $amount, Model $purchaser, ?Model $recipient = null): GiftCard;
    public function createBulk(int $count, int $amount, array $options = []): Collection;
    
    // Activation
    public function activate(string $code): GiftCard;
    public function activateWithDelay(string $code, Carbon $activateAt): GiftCard;
    
    // Balance Operations
    public function checkBalance(string $code): int;
    public function topUp(string $code, int $amount, Model $actor): GiftCard;
    public function transfer(string $code, Model $newRecipient, Model $actor): GiftCard;
    public function merge(array $codes, Model $actor): GiftCard;
    
    // Redemption
    public function redeem(string $code, int $amount, Model $reference): GiftCardTransaction;
    public function refund(string $code, int $amount, Model $reference): GiftCardTransaction;
    
    // Queries
    public function findByCode(string $code): ?GiftCard;
    public function getByRecipient(Model $recipient): Collection;
    public function getExpiring(int $days = 30): Collection;
}
```

---

## Facade Methods

```php
use AIArmada\Vouchers\Facades\GiftCard;

// Issue new gift card
$card = GiftCard::issue([
    'type' => 'standard',
    'initial_balance' => 10000, // RM100
    'recipient' => $user,
]);

// Check balance
$balance = GiftCard::checkBalance('GC-XXXX-XXXX');

// Apply to cart
Cart::applyGiftCard('GC-XXXX-XXXX');

// Get remaining to pay
$remaining = Cart::getRemainingBalance();
```

---

## Implementation Phases

### Phase 1: Core Infrastructure
- [ ] `GiftCard` model
- [ ] `GiftCardTransaction` model
- [ ] `GiftCardService` service
- [ ] Database migrations

### Phase 2: Cart Integration
- [ ] Balance deduction operator in cart
- [ ] `GiftCardCondition` class
- [ ] `InteractsWithGiftCards` trait
- [ ] Multi-card stacking support

### Phase 3: Checkout Integration
- [ ] `GiftCardPaymentHandler`
- [ ] `GiftCardRefundHandler`
- [ ] Split payment support

### Phase 4: Advanced Features
- [ ] PIN verification
- [ ] Balance transfer
- [ ] Card merging
- [ ] Expiry management jobs

### Phase 5: Filament UI
- [ ] Gift card resource
- [ ] Balance check widget
- [ ] Transaction history
- [ ] Bulk issuance tool

---

## Navigation

**Previous:** [03-targeting-engine.md](03-targeting-engine.md)  
**Next:** [05-campaign-management.md](05-campaign-management.md)
