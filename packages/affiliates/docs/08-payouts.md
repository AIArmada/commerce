---
title: Payouts
---

# Payout Management

The package provides comprehensive payout management including batch processing, multiple payment methods, holds, and reconciliation.

## Payout Flow

```
Conversions → Maturity Period → Eligible for Payout → Batch Created → Processing → Paid
     ↓             ↓                    ↓                   ↓             ↓
  Pending      Holding           Available Balance      Scheduled    Completed
```

## Creating Payouts

### Using the Service

```php
use AIArmada\Affiliates\Services\AffiliatePayoutService;

$service = app(AffiliatePayoutService::class);

// Get eligible conversions
$conversions = $service->getPayableConversions($affiliate);

// Create payout batch
$payout = $service->createPayout($affiliate, [
    'method' => PayoutMethodType::PayPal,
    'notes' => 'Monthly payout for January',
]);

// The payout includes all eligible approved conversions
// that have passed the maturity period
```

### Manual Creation

```php
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Enums\PayoutStatus;

$payout = AffiliatePayout::create([
    'reference' => 'PO-' . now()->format('Ymd') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT),
    'status' => PayoutStatus::Pending,
    'total_minor' => $totalAmount,
    'currency' => 'USD',
    'payee_type' => $affiliate->getMorphClass(),
    'payee_id' => $affiliate->getKey(),
    'scheduled_at' => now()->addDays(3),
]);

// Link conversions to payout
$conversions->each(fn ($c) => $c->update(['affiliate_payout_id' => $payout->id]));
```

## Payout Statuses

```php
use AIArmada\Affiliates\Enums\PayoutStatus;

PayoutStatus::Pending;     // Awaiting processing
PayoutStatus::Scheduled;   // Scheduled for future date
PayoutStatus::Processing;  // Currently being processed
PayoutStatus::Completed;   // Successfully paid
PayoutStatus::Failed;      // Payment failed
PayoutStatus::Cancelled;   // Cancelled by admin
```

## Payout Methods

### Configuring Methods

```php
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Enums\PayoutMethodType;

// PayPal
AffiliatePayoutMethod::create([
    'affiliate_id' => $affiliate->id,
    'type' => PayoutMethodType::PayPal,
    'is_default' => true,
    'is_verified' => true,
    'details' => [
        'email' => 'affiliate@paypal.com',
    ],
]);

// Bank Transfer
AffiliatePayoutMethod::create([
    'affiliate_id' => $affiliate->id,
    'type' => PayoutMethodType::BankTransfer,
    'is_default' => false,
    'details' => [
        'account_name' => 'John Partner',
        'account_number' => '****1234',
        'routing_number' => '****5678',
        'bank_name' => 'First National Bank',
    ],
]);

// Stripe Connect
AffiliatePayoutMethod::create([
    'affiliate_id' => $affiliate->id,
    'type' => PayoutMethodType::Stripe,
    'details' => [
        'account_id' => 'acct_1234567890',
    ],
]);
```

### Available Method Types

```php
use AIArmada\Affiliates\Enums\PayoutMethodType;

PayoutMethodType::PayPal;
PayoutMethodType::Stripe;
PayoutMethodType::BankTransfer;
PayoutMethodType::Check;
PayoutMethodType::Crypto;
PayoutMethodType::Manual;
```

## Payout Holds

Place temporary holds on affiliate payouts:

```php
use AIArmada\Affiliates\Models\AffiliatePayoutHold;

// Create hold
$hold = AffiliatePayoutHold::create([
    'affiliate_id' => $affiliate->id,
    'reason' => 'Fraud investigation pending',
    'amount_minor' => 50000, // $500 on hold
    'held_at' => now(),
]);

// Release hold
$hold->update([
    'released_at' => now(),
    'release_notes' => 'Investigation complete, no issues found',
]);

// Check for active holds
$hasHolds = $affiliate->payoutHolds()
    ->whereNull('released_at')
    ->exists();
```

## Maturity Period

Conversions must mature before payout eligibility:

```php
use AIArmada\Affiliates\Services\CommissionMaturityService;

$service = app(CommissionMaturityService::class);

// Process matured conversions (moves from holding to available)
$processed = $service->processMaturedConversions();

// Check if specific conversion is mature
$isMature = $service->isMature($conversion);

// Get maturity date
$maturesAt = $service->getMaturityDate($conversion);
// Default: 30 days after conversion (configurable)
```

Configure in `config/affiliates.php`:

```php
'payouts' => [
    'maturity_days' => 30, // Days before commission is payable
],
```

## Balance Management

Track affiliate balances in real-time:

```php
use AIArmada\Affiliates\Models\AffiliateBalance;

$balance = $affiliate->balance;

// Available for withdrawal
$available = $balance->available_minor; // In cents

// Pending approval
$pending = $balance->pending_minor;

// On hold (maturity, fraud review)
$holding = $balance->holding_minor;

// Total lifetime earnings
$lifetime = $balance->lifetime_minor;
```

## Processing Payouts

### PayPal Integration

```php
// Configure in config/affiliates.php
'payouts' => [
    'paypal' => [
        'client_id' => env('AFFILIATES_PAYPAL_CLIENT_ID'),
        'client_secret' => env('AFFILIATES_PAYPAL_CLIENT_SECRET'),
        'sandbox' => env('AFFILIATES_PAYPAL_SANDBOX', true),
    ],
],
```

### Stripe Integration

```php
'payouts' => [
    'stripe' => [
        'secret_key' => env('AFFILIATES_STRIPE_SECRET_KEY'),
    ],
],
```

### Processing with PayoutProcessorFactory

```php
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;

$factory = app(PayoutProcessorFactory::class);

// Get processor for payout method
$processor = $factory->make($payout->method);

// Process payout
$result = $processor->process($payout);

if ($result->isSuccessful()) {
    $payout->update([
        'status' => PayoutStatus::Completed,
        'paid_at' => now(),
        'metadata' => array_merge($payout->metadata ?? [], [
            'transaction_id' => $result->getTransactionId(),
        ]),
    ]);
}
```

## Payout Events

Track payout history with events:

```php
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;

// Events are automatically recorded
$events = $payout->events()->orderBy('created_at')->get();

// Manual event recording
AffiliatePayoutEvent::create([
    'affiliate_payout_id' => $payout->id,
    'event_type' => 'processing_started',
    'metadata' => [
        'processor' => 'paypal',
        'batch_id' => 'BATCH-123',
    ],
]);
```

## Reconciliation

Reconcile payouts with external payment data:

```php
use AIArmada\Affiliates\Services\PayoutReconciliationService;

$service = app(PayoutReconciliationService::class);

// Get unreconciled payouts
$pending = $service->getUnreconciledPayouts();

// Reconcile with provider data
$result = $service->reconcile($payout, [
    'transaction_id' => 'TXN-456',
    'actual_amount' => 49850, // After fees
    'provider_fee' => 150,
    'settled_at' => now(),
]);
```

## Artisan Commands

### Process Scheduled Payouts

```bash
php artisan affiliates:process-payouts
```

### Process Commission Maturity

```bash
php artisan affiliates:process-maturity
```

### Export Payout Data

```bash
php artisan affiliates:export-payouts --from=2024-01-01 --to=2024-01-31
```

## Multi-Level Payouts

For MLM/network structures, distribute commissions to uplines:

```php
// Configure in config/affiliates.php
'payouts' => [
    'multi_level' => [
        'enabled' => true,
        'levels' => [0.10, 0.05, 0.02], // 10%, 5%, 2% of commission
    ],
],
```

When a conversion is recorded, the NetworkService automatically calculates and credits upline affiliates:

```php
use AIArmada\Affiliates\Services\NetworkService;

$networkService = app(NetworkService::class);

// Calculate multi-level commissions
$commissions = $networkService->calculateMultiLevelCommissions(
    $conversion,
    $levels = [0.10, 0.05, 0.02],
);

// Returns array of [affiliate_id => commission_minor]
```
