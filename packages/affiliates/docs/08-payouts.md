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
    'metadata' => ['note' => 'Monthly payout for January'],
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
use AIArmada\Affiliates\States\PendingPayout;

$payout = AffiliatePayout::create([
    'reference' => 'PO-' . now()->format('Ymd') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT),
    'status' => PendingPayout::class,
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

Payout status is a Spatie state (`States\PayoutStatus`); assign state classes on write:

```php
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;

PendingPayout::class;     // Awaiting processing
ProcessingPayout::class;  // Currently being processed
CompletedPayout::class;   // Successfully paid
FailedPayout::class;      // Payment failed
CancelledPayout::class;   // Cancelled by admin
```

`scheduled_at` is still available on the model and used by the scheduled-payout command, but there is no separate `Scheduled` state. The `Enums\PayoutStatus` enum mirrors these cases for reads and interop.

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
    'verified_at' => now(),
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
    'type' => PayoutMethodType::StripeConnect,
    'details' => [
        'account_id' => 'acct_1234567890',
    ],
]);
```

### Available Method Types

```php
use AIArmada\Affiliates\Enums\PayoutMethodType;

PayoutMethodType::BankTransfer;
PayoutMethodType::PayPal;
PayoutMethodType::StripeConnect;
PayoutMethodType::Wise;
PayoutMethodType::Payoneer;
PayoutMethodType::Check;
PayoutMethodType::Wire;
PayoutMethodType::Crypto;
```

## Payout Holds

Place temporary holds on affiliate payouts:

```php
use AIArmada\Affiliates\Models\AffiliatePayoutHold;

// Create hold
$hold = AffiliatePayoutHold::create([
    'affiliate_id' => $affiliate->id,
    'reason' => 'Fraud investigation pending',
    'notes' => '$500 under review',
    'placed_by' => $admin->id,
]);

// Release hold
$hold->update([
    'released_at' => now(),
    'notes' => 'Investigation complete, no issues found',
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

// Mature a specific conversion (returns bool)
$matured = MatureConversion::run($conversion);
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
use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;

$factory = app(PayoutProcessorFactory::class);

// Get processor for a payout method type
$processor = $factory->make(PayoutMethodType::PayPal);

// Process payout
$result = $processor->process($payout);

if ($result->isSuccess()) {
    $payout = UpdatePayoutStatus::run($payout, 'completed', 'Provider outcome: completed', [
        'provider' => $result->metadata['provider'] ?? null,
        'provider_status' => $result->getStatus(),
    ]);

    // The 4th argument lands ONLY on the payout event. Persist the
    // provider reference on the payout itself, like the real
    // ProcessAffiliatePayout path does.
    $payout->forceFill([
        'external_reference' => $result->externalReference,
        'metadata' => array_merge($payout->metadata ?? [], [
            'provider' => $result->metadata['provider'] ?? null,
            'provider_status' => $result->getStatus(),
        ]),
    ])->save();
}
```

> [!WARNING]
> Never complete a payout with a direct status write (`$payout->update(['status' => ...])`): it bypasses the completion eligibility gate, the Approved-only conversion sync, the payout event, and the operation sync — leaving conversions linked-but-approved against a payout that reported the money as sent. Always complete through `UpdatePayoutStatus::run($payout, 'completed', ...)` (or `ProcessAffiliatePayout::handle($payout)` for the full claim/processor flow).

## Payout Events

Track payout history with events:

```php
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;

// Events are automatically recorded
$events = $payout->events()->orderBy('created_at')->get();

// Manual event recording
AffiliatePayoutEvent::create([
    'affiliate_payout_id' => $payout->id,
    'from_status' => 'pending',
    'to_status' => 'processing',
    'notes' => 'Processing started',
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

`generateReport()` folds amounts without blending currencies. Single-currency sets pass raw sums through; mixed sets convert to `affiliates.currency.default` with the rates effective at the period end, and legs without an exchange rate null the whole total — read `by_currency` for the exact per-currency amounts:

```php
$report = $service->generateReport('2026-01-01', '2026-03-31');

$report['summary']['total_amount_minor']; // int|null, converted when mixed
$report['summary']['currency'];           // denomination of the totals
$report['summary']['converted'];          // true when FX math was applied
$report['summary']['conversion'];         // ['currency', 'as_of', 'source'] provenance, or null
$report['by_currency']['USD']['total_minor'];
```

Use `summarizePayouts($payouts, $start, $end)` for the same settlement summary over an explicit payout set (for example an owner-scoped batch). Every payout holds exactly one currency: linking a mismatched conversion throws, and `reconcilePayout()` refuses to complete a mixed payout — `currency_mismatches` in the report points at corrupt rows.

## Artisan Commands

### Process Scheduled Payouts

```bash
php artisan affiliates:process-payouts
```

Without `--min-amount` every balance is judged against its own `minimum_payout_minor` (which inherits the per-currency floor from `payouts.minimum_amounts_by_currency`, falling back to `payouts.minimum_amount`). Pass `--min-amount=N` to impose an additional floor of N minor units in each balance's own currency.

### Process Commission Maturity

```bash
php artisan affiliates:process-maturity
```

### Export Payout Data

```bash
php artisan affiliates:payout:export PAY-REF-1234 --path=/path/to/payout.csv
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
