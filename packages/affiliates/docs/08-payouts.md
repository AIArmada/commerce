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
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAdministration;

// Create payout from conversion IDs
$payoutId = app(PayoutAdministration::class)->create((string) $affiliate->getKey(), $conversionIds, [
    'payee_type' => $affiliate->getMorphClass(),
    'payee_id' => $affiliate->getKey(),
    'metadata' => ['note' => 'Monthly payout for January'],
]);
```

`CreatePayout` reads only `reference`, `status`, `currency`, `metadata`,
`payee_type`, `payee_id`, `scheduled_at`, `paid_at`, `owner_type`, and
`owner_id` from `$attributes`. There is no `method` or `notes` key — both are
silently ignored. The payout method is chosen later at processing time. A supplied payee must identify the same affiliate whose conversions are being reserved; the canonical affiliate morph type and ID are persisted. Substituted payees are rejected before reservation and again before provider submission or completion.

### One Currency Per Payout

Every payout reserves exactly one per-currency balance, so all conversions in a payout must share one `commission_currency`. Mixed-currency sets throw an `InvalidArgumentException` instead of summing silently — schedule one payout per currency. A `currency` attribute that disagrees with the conversions also throws.

```php
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAdministration;

// Throws: conversions span USD and MYR.
$payoutId = app(PayoutAdministration::class)->create((string) $affiliate->getKey(), $mixedConversionIds, [
    'payee_type' => $affiliate->getMorphClass(),
    'payee_id' => $affiliate->getKey(),
]);

// Correct: group conversion IDs by commission_currency first, then run
// one payout per group.
foreach ($conversionIdsByCurrency as $currency => $ids) {
    app(PayoutAdministration::class)->create((string) $affiliate->getKey(), $ids, [
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);
}

// Update payout status
app(PayoutAdministration::class)->updateStatus($payoutId, 'completed', 'Processed successfully');
```

### Purpose Gate and Reward-Award Settlement

Payouts are commercial by default. `CreatePayout` and the shared
`AssertPayoutCompletable` gate require an affiliate with unset or `general`
purpose. Provider submission checks this gate before sending money without
recording an override completion timestamp. The gate also protects manual
status updates and provider reconciliation, so a purpose-tagged payout cannot
bypass the check through a second completion path.

Linked conversions must still be approved. For payouts backed by a balance
reservation, their count, commission total, and currency must match the
unreleased operation and payout amount. Editing payout amounts or manually
adding conversions does not change the reservation and blocks settlement.

The only exception is a payout containing exclusively `reward_award` backing
conversions, and it is controlled by the single switch
`affiliates.payouts.reward_award_settlement.enabled`. The package default is
`true` for consumers that intentionally settle reward entitlements. Set it to
`false` to close the seam, including completion of already-created
reward-award payouts. Any mixed payout containing a purchase or other
commercial conversion is subject to the normal commercial-purpose gate.

For Ilmu360, the host pins this switch off in `config/affiliates.php`; cash
referral fulfillment is also disabled in `config/referrals.php`.

### Record-only payout rows

Creating an `AffiliatePayout` model directly records a payout row only. It
does not reserve an affiliate balance, claim conversions, or synchronize
conversion status. Do not use direct model creation or manual conversion
linking to settle commission. Use `PayoutAdministration::create()` so eligibility, owner,
currency, balance reservation, and atomic conversion claims are enforced.

Existing manually created payout rows are not rewritten or backfilled. Before
creating replacement settlements, reconcile each outstanding row with the
payment processor and affiliate balance; do not assume its amount was
reserved just because the row is pending.

## Payout Statuses

Payout status is a Spatie state (`States\PayoutStatus`); assign state classes on write:

```php
use AIArmada\Affiliates\Features\Payouts\States\CancelledPayout;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;

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
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;

$methods = app(PayoutMethodAdministration::class);

$paypalId = $methods->create($affiliate->id, 'paypal', [
    'email' => 'affiliate@paypal.com',
], default: true);
$methods->verify($affiliate->id, $paypalId);

$methods->create($affiliate->id, 'bank_transfer', [
    'account_name' => 'John Partner',
    'account_number' => '123456789',
    'bank_name' => 'First National Bank',
]);

$methods->create($affiliate->id, 'stripe_connect', [
    'stripe_account_id' => 'acct_1234567890',
]);
```

> **warning:**
> `is_verified` is not a column. Verification is the `verified_at` timestamp;
> `create()` would drop an `is_verified` key silently.

### Available Method Types

```php
use AIArmada\Affiliates\Enums\PayoutMethodType;

PayoutMethodType::Manual;        // 'manual'
PayoutMethodType::BankTransfer;  // 'bank_transfer'
PayoutMethodType::PayPal;        // 'paypal'
PayoutMethodType::StripeConnect; // 'stripe_connect'
PayoutMethodType::Wise;          // 'wise'
PayoutMethodType::Payoneer;      // 'payoneer'
PayoutMethodType::Check;         // 'check'
PayoutMethodType::Wire;          // 'wire'
PayoutMethodType::Crypto;        // 'crypto'
```

There is no `Stripe` case; use `StripeConnect`. Both
`PayoutMethodType::Manual` and the string `manual` resolve through
`PayoutProcessorFactory` to `ManualPayoutProcessor`.

Default method changes are blocked while a provider operation is submitting, unknown or submitted. Resolve that operation first. Provider details are validated and encrypted; changing the destination clears verification.

## Payout Holds

Place temporary holds on affiliate payouts:

```php
use AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldAdministration;

$holds = app(PayoutHoldAdministration::class);
$holdId = $holds->place(
    (string) $affiliate->getKey(),
    'Fraud investigation pending',
    notes: 'Ticket FR-42',
    expiresAt: now()->addDays(30)->toImmutable(),
    placedBy: 'admin-1',
);

$holds->release((string) $affiliate->getKey(), $holdId, 'Investigation complete, no issues found');

$hasHolds = app(\AIArmada\Affiliates\Features\Payouts\Api\PayoutHoldReader::class)->hasActive((string) $affiliate->getKey());
```

> **warning:**
> An active hold blocks scheduled and manual reservations, provider submission, and completion until released or expired. A disabled-affiliate override does not bypass a hold.
>
> A hold is a gate, not an amount. `amount_minor` and `held_at` are not
> columns and would be silently dropped. Use `expires_at` for the hold
> deadline and `notes` for the release note.

## Maturity Period

Conversions must mature before payout eligibility:

### Using Actions (Canonical API)

```php
use AIArmada\Affiliates\Features\Conversions\Api\ConversionMaturity;

// Promote all qualified conversions that have reached maturity
$results = app(ConversionMaturity::class)->process();

// Mature a specific conversion (returns bool)
$matured = app(ConversionMaturity::class)->mature($conversionId);
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
use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;

$reader = app(BalanceReader::class);

// All balances for the affiliate, keyed by row.
$balances = $reader->forAffiliate((string) $affiliate->getKey());

// The USD balance, or null when the affiliate never earned USD.
$balance = $reader->find((string) $affiliate->getKey(), 'USD');

// Available for withdrawal
$available = $balance?->availableMinor; // In cents

// On hold (maturity, fraud review)
$holding = $balance?->holdingMinor;

// Total lifetime earnings
$lifetime = $balance?->lifetimeEarningsMinor;

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

### Processing payouts

```php
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAdministration;

$result = app(PayoutAdministration::class)->process($payoutId);
```

The action claims the payout, validates eligibility, constructs an immutable
provider request, and persists the result only while its lease is current.
Provider drivers accept `PayoutRequest` and return `PayoutResult` or
`PayoutStatusResult`; they do not read or write Eloquent models.

`AffiliatePayout` has no `method` column — resolve the method from the
affiliate's default payout method through `PayoutMethodReader`. `PayoutResult` exposes
`isSuccess()` / `isPending()` / `isUnknown()` and `getStatus()`; there is no
`isSuccessful()` or `getTransactionId()`.

> [!WARNING]
> Never complete a payout with a direct status write (`$payout->update(['status' => ...])`): it bypasses the completion eligibility gate, the Approved-only conversion sync, the payout event, and the operation sync — leaving conversions linked-but-approved against a payout that reported the money as sent. Always complete through `app(PayoutAdministration::class)->updateStatus($payoutId, 'completed', ...)` (or `app(PayoutAdministration::class)->process($payoutId)` for the full claim/processor flow).

## Payout Events

Payout actions record events in the payout transaction. Read the latest owner-scoped history as immutable records:

```php
use AIArmada\Affiliates\Features\Payouts\Api\PayoutEventHistory;

$events = app(PayoutEventHistory::class)->forPayout($payoutId, limit: 100);
```

> **warning:**
> There is no `event_type` column; the transition is recorded as
> `from_status` / `to_status`. An `event_type` key would be dropped silently.

## Reconciliation

Reconcile payouts with external payment data:

```php
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliationQueries;

$service = app(PayoutReconciliationQueries::class);

// Get payouts still needing reconciliation
$pending = $service->payoutIdsNeedingReconciliation();

// Reconcile with a provider status string
$changed = app(\AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation::class)->reconcile($payoutId, 'paid', [
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

Use `summarizePayoutIds($payoutIds, $start, $end)` for the same settlement summary over an explicit payout set (for example an owner-scoped batch). Conversions linked to a payout must share one currency and one affiliate: linking a conversion with a different currency or affiliate throws, and provider submission rechecks the linked affiliate set before sending. `PayoutReconciliation::reconcile()` also refuses to complete a mixed-currency payout — `currency_mismatches` in the report points at corrupt rows.

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
php artisan affiliates:payout:export PAY-REF-1234
php artisan affiliates:payout:export 2d8ce0f4-... --path=/path/to/payout.csv
```

Exports a single payout (reference or UUID) with its linked conversions. The local CSV writer publishes atomically and fails on ambiguous references or filesystem errors. There is
no `affiliates:export-payouts` command and no `--from`/`--to` date range.

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

When a conversion is recorded, the upline API exposes the upline chain so multi-level commissions credit upline affiliates:

```php
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;

$uplineService = app(UplineApi::class);

// Upline chain for a conversion's affiliate
$uplines = $uplineService->ancestors((string) $conversion->affiliate_id);
```


## Atomic scheduled claims

`affiliates:process-payouts` scans active affiliates in chunks. Both the Filament and console sweeps invoke the Payouts feature. For each eligible affiliate, `ClaimScheduledPayout` locks the affiliate and balance, rechecks active holds and active payouts, locks approved unlinked conversions, creates one unique operation, reserves the balance, and links the conversions through the Conversions reservation API in a single transaction, checking the linked count and amount before committing. Repeated or concurrent workers therefore observe the existing pending payout and cannot reserve the same funds twice. External Stripe, PayPal, or manual processing occurs after the claim transaction.

## Provider idempotency and reconciliation

Every provider submission is tied to the durable `AffiliatePayoutOperation`. Stripe receives the operation UUID as its `Idempotency-Key`; PayPal receives a stable 30-character identity derived from that UUID as both `sender_batch_id` and `sender_item_id`. Provider calls use bounded connect/read timeouts and retries. Retryable HTTP failures, transport exceptions, and ambiguous duplicate responses are recorded as `unknown`, while the payout remains processing for reconciliation. Raw provider or exception messages are never persisted or shown to operators. Stripe reversals reuse a deterministic reversal idempotency key and are recorded locally as `reversed`.

`PayoutReconciliationQueries::auditAffiliateBalance()` compares available
balance with approved commissions, open payout reservations, completed payout
debits, and paid conversions attached to completed payouts. Reservation and
debit amounts come from unreleased operations with a balance sequence, using
the operation's affiliate and currency even when the payout has another payee.
Directly marked paid conversions are already removed from the approved total; a later direct
paid reversal remains a negative available-balance clawback. This audit does
not infer reservations for record-only payout rows.

### Verify or select an existing payout method

Call the owning feature API from authorized administrative code. Both operations require the affiliate ID and the payout method ID.

```php
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;

$methods = app(PayoutMethodAdministration::class);
$methods->verify($affiliateId, $methodId);
$methods->selectDefault($affiliateId, $methodId);
```
