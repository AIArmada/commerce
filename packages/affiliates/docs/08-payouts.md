---
title: Payouts
---

# Payout Management

The package provides comprehensive payout management including batch processing, multiple payment methods, holds, and reconciliation.

## Payout Flow

```
Conversions → Qualified / Holding → Maturity Check → Approved / Available → Payout Created → Processing → Completed
         ↓                ↓                 ↓                    ↓               ↓
     Pending         holding_minor     available_minor       Pending        Failed / Cancelled
```

## Creating Payouts

### Using Actions (Canonical API)

```php
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;

// Create payout from conversion IDs
$payout = CreatePayout::run($conversionIds, [
    'payee_type' => $affiliate->getMorphClass(),
    'payee_id' => $affiliate->getKey(),
    'method' => PayoutMethodType::PayPal,
    'notes' => 'Monthly payout for January',
]);
```

### One Currency Per Payout

Every payout reserves exactly one per-currency balance, so all conversions in a payout must share one `commission_currency`. Mixed-currency sets throw an `InvalidArgumentException` instead of summing silently — schedule one payout per currency. A `currency` attribute that disagrees with the conversions also throws.

```php
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;

// Throws: conversions span USD and MYR.
$payout = CreatePayout::run($mixedConversionIds, [
    'payee_type' => $affiliate->getMorphClass(),
    'payee_id' => $affiliate->getKey(),
]);

// Correct: group conversion IDs by commission_currency first, then run
// one payout per group.
foreach ($conversionIdsByCurrency as $currency => $ids) {
    CreatePayout::run($ids, [
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);
}

// Update payout status
UpdatePayoutStatus::run($payout, 'completed', 'Processed successfully');
```

### Manual Creation

```php
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Enums\PayoutStatus;

$payout = AffiliatePayout::create([
    'reference' => 'PO-' . now()->format('Ymd') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT),
    'status' => PayoutStatus::Pending,
    'total_minor' => $totalAmount,
    'currency' => $affiliate->currency,
    'payee_type' => $affiliate->getMorphClass(),
    'payee_id' => $affiliate->getKey(),
    'scheduled_at' => now()->addDays(3),
]);

// Link conversions to payout
$conversions->each(fn ($c) => $c->update(['affiliate_payout_id' => $payout->id]));
```

> **Warning:** manual linking bypasses the one-currency-per-payout guard in `CreatePayout`. Only attach conversions whose `commission_currency` matches the payout `currency`, and prefer the action so balance reservation stays consistent.

## Payout Statuses

```php
use AIArmada\Affiliates\Enums\PayoutStatus;

PayoutStatus::Pending;     // Awaiting processing
PayoutStatus::Processing;  // Currently being processed
PayoutStatus::Completed;   // Successfully paid
PayoutStatus::Failed;      // Payment failed
PayoutStatus::Cancelled;   // Cancelled by admin
```

`scheduled_at` is still available on the model and used by the scheduled-payout command, but there is no separate `Scheduled` enum state.

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

### Using Actions (Canonical API)

```php
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Actions\Conversions\MatureConversion;

// Promote all qualified conversions that have reached maturity
$results = ProcessConversionMaturity::run();

// Mature a specific conversion
$conversion = MatureConversion::run($conversion);
```

Configure in `config/affiliates.php`:

```php
'payouts' => [
    'maturity_days' => 30, // Days before commission is payable
],
```

## Balance Management

Balances are per currency: each affiliate holds one `AffiliateBalance` row per currency earned. Conversion accounting routes every approved commission into the balance matching its `commission_currency`, creating the row on first use.

```php
use AIArmada\Affiliates\Models\AffiliateBalance;

// All balances for the affiliate, keyed by row.
$balances = $affiliate->balances;

// The USD balance, or null when the affiliate never earned USD.
$balance = $affiliate->balanceFor('USD');

// Available for withdrawal
$available = $balance->available_minor; // In cents

// On hold (maturity, fraud review)
$holding = $balance->holding_minor;

// Total lifetime earnings
$lifetime = $balance->lifetime_earnings_minor;

// Combined holding + available balance
$total = $balance->getTotalBalanceMinor();

// True when available balance meets the minimum payout threshold
$canRequestPayout = $balance->canRequestPayout();
```

The current maturity flow uses `holding_minor` for pre-payout commission state. There is no separate `pending_minor` balance field in the current model.

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

// Get payouts still needing reconciliation
$pending = $service->getPayoutsNeedingReconciliation();

// Reconcile with a provider status string
$changed = $service->reconcilePayout($payout, 'paid', [
    'reference' => 'TXN-456',
    'status' => 'settled',
]);
```

Provider statuses map case-insensitively: `completed`/`paid`/`success`/`succeeded` → completed, `failed`/`declined`/`rejected`/`error` → failed, `pending`/`created` → pending, `processing`/`in_progress` → processing, `cancelled`/`canceled` → cancelled. Unknown strings return `false` without touching the payout.

Reconciliation is guarded by the payout state machine: the payout row is locked, and only declared transitions run — stale or out-of-order provider events (including ones targeting terminal payouts) are ignored rather than forced, so a `Completed` payout can never be resurrected to `Failed`. On completion the linked conversions sync to `Paid`; on failure or cancellation reserved funds are released in the same transaction and conversions detach back to `Approved`. The boolean return tells you whether anything changed.

`generateReport()` folds amounts without blending currencies. Single-currency sets pass raw sums through; mixed sets convert to `affiliates.currency.default`, and legs without an exchange rate null the whole total — read `by_currency` for the exact per-currency amounts:

```php
$report = $service->generateReport('2026-01-01', '2026-03-31');

$report['summary']['total_amount_minor']; // int|null, converted when mixed
$report['summary']['currency'];           // denomination of the totals
$report['summary']['converted'];          // true when FX math was applied
$report['by_currency']['USD']['total_minor'];
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

For MLM/upline structures, distribute commissions to uplines:

```php
// Configure in config/affiliates.php
'payouts' => [
    'multi_level' => [
        'enabled' => true,
        'levels' => [0.10, 0.05, 0.02], // 10%, 5%, 2% of commission
    ],
],
```

When a conversion is recorded, the UplineService resolves the upline chain so multi-level commissions credit upline affiliates:

```php
use AIArmada\Affiliates\Services\UplineService;

$uplineService = app(UplineService::class);

// Upline chain for a conversion's affiliate
$uplines = $uplineService->getUpline($conversion->affiliate);
```


## Atomic scheduled claims

`affiliates:process-payouts` scans active affiliates in chunks. For each eligible affiliate, `ClaimScheduledPayout` locks the affiliate and balance, rechecks active holds and active payouts, locks approved unlinked conversions, creates one unique operation, reserves the balance, and links the conversions in a single transaction. Repeated or concurrent workers therefore observe the existing pending payout and cannot reserve the same funds twice. External Stripe, PayPal, or manual processing occurs after the claim transaction.

## Provider idempotency and reconciliation

Every provider submission is tied to the durable `AffiliatePayoutOperation`. Stripe receives the operation UUID as its `Idempotency-Key`; PayPal receives a stable 30-character identity derived from that UUID as both `sender_batch_id` and `sender_item_id`. Provider calls use bounded connect/read timeouts and retries. Retryable HTTP failures, transport exceptions, and ambiguous duplicate responses are recorded as `unknown`, while the payout remains processing for reconciliation. Raw provider or exception messages are never persisted or shown to operators. Stripe reversals reuse a deterministic reversal idempotency key and are recorded locally as `reversed`.
