# Automated Payout System

> **Document:** 07 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🟡 40% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Build a comprehensive **automated payout system** that handles scheduled payouts, multiple payment methods, tax document generation, compliance workflows, and reconciliation—all with minimal manual intervention.

---

## Current State ✅

### Implemented Features

1. **AffiliatePayout Model**
   - Full model with UUID primary key
   - Status tracking (pending, processing, completed, failed)
   - Amount storage in minor units
   - Reference generation
   - Relationship to Affiliate

2. **AffiliatePayoutEvent Model**
   - Payout event tracking
   - Status change history
   - Metadata storage

3. **AffiliatePayoutService**
   - `createPayout()` - Create new payout record
   - `processPayout()` - Process individual payout
   - `updateStatus()` - Status transitions
   - Event dispatching on status changes

4. **PayoutExportService (Filament)**
   - CSV export of payout records
   - Filterable exports by affiliate, status, date
   - Bulk export support

5. **AffiliatePayoutResource (Filament)**
   - Full CRUD for payouts
   - Status badges and filters
   - Affiliate relationship display

6. **Configuration**
   ```php
   'payouts' => [
       'minimum_amount' => 100_00, // $100 minimum
       'currency' => 'USD',
       'holding_period_days' => 30,
   ],
   ```

### Limitations (To Be Addressed)

- No automated batch processing
- No payment provider integrations (Stripe, PayPal, etc.)
- No payout method management (affiliates can't set bank details)
- No tax document generation (1099, W-9)
- No commission holding period enforcement
- No payout holds/blocks
- No reconciliation service
- No scheduled commands

---

## Payout Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   PAYOUT PIPELINE                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Conversions ──► Holding ──► Available ──► Payout Queue     │
│                 (14-30 days)                                │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              PAYOUT PROCESSOR                        │    │
│  │                                                      │    │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐       │    │
│  │  │ Bank   │ │ PayPal │ │ Stripe │ │ Crypto │       │    │
│  │  │Transfer│ │        │ │ Connect│ │        │       │    │
│  │  └────────┘ └────────┘ └────────┘ └────────┘       │    │
│  │                                                      │    │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐       │    │
│  │  │ Wise   │ │Payoneer│ │ Check  │ │ Wire   │       │    │
│  │  │        │ │        │ │        │ │        │       │    │
│  │  └────────┘ └────────┘ └────────┘ └────────┘       │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ──► Reconciliation ──► Tax Documents ──► Reporting         │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Enhanced AffiliatePayoutService

```php
class AffiliatePayoutService
{
    public function __construct(
        private PayoutProcessorFactory $processorFactory,
        private PayoutEligibilityChecker $eligibilityChecker,
        private TaxDocumentService $taxService,
        private PayoutNotificationService $notifications,
    ) {}
    
    /**
     * Process scheduled payouts for all eligible affiliates
     */
    public function processScheduledPayouts(): PayoutBatchResult
    {
        $eligibleAffiliates = $this->getEligibleAffiliates();
        $batch = $this->createPayoutBatch();
        
        foreach ($eligibleAffiliates as $affiliate) {
            try {
                $payout = $this->createPayoutForAffiliate($affiliate, $batch);
                $this->processPayoutItem($payout);
            } catch (PayoutException $e) {
                $this->handlePayoutFailure($affiliate, $e, $batch);
            }
        }
        
        return $this->finalizeBatch($batch);
    }
    
    /**
     * Get affiliates eligible for payout
     */
    private function getEligibleAffiliates(): Collection
    {
        return Affiliate::query()
            ->whereHas('balance', fn ($q) => $q
                ->where('available_minor', '>=', DB::raw('minimum_payout_minor'))
            )
            ->whereHas('payoutMethods', fn ($q) => $q
                ->where('is_verified', true)
                ->where('is_default', true)
            )
            ->where('status', AffiliateStatus::Active)
            ->where('is_payout_enabled', true)
            ->whereDoesntHave('payoutHolds')
            ->get();
    }
}
```

---

## Payout Processors

### PayoutProcessorInterface

```php
interface PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult;
    
    public function getStatus(AffiliatePayout $payout): PayoutStatus;
    
    public function cancel(AffiliatePayout $payout): bool;
    
    public function getEstimatedArrival(AffiliatePayout $payout): ?Carbon;
    
    public function getFees(int $amountMinor, string $currency): int;
    
    public function validateDetails(array $details): ValidationResult;
}
```

### Stripe Connect Processor

```php
class StripeConnectProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        $payoutMethod = $payout->payoutMethod;
        $stripeAccountId = $payoutMethod->details['stripe_account_id'];
        
        try {
            $transfer = Stripe::transfers()->create([
                'amount' => $payout->net_amount_minor,
                'currency' => strtolower($payout->currency),
                'destination' => $stripeAccountId,
                'transfer_group' => $payout->batch_id,
                'metadata' => [
                    'payout_id' => $payout->id,
                    'affiliate_id' => $payout->affiliate_id,
                ],
            ]);
            
            return PayoutResult::success(
                externalReference: $transfer->id,
                metadata: ['transfer_id' => $transfer->id]
            );
        } catch (StripeException $e) {
            return PayoutResult::failure(
                reason: $e->getMessage(),
                code: $e->getStripeCode()
            );
        }
    }
}
```

---

## Commission Holding Period

### CommissionMaturityService

```php
class CommissionMaturityService
{
    /**
     * Move mature commissions from holding to available
     */
    public function matureCommissions(): int
    {
        $holdingPeriod = config('affiliates.commission_holding_days', 30);
        $cutoffDate = now()->subDays($holdingPeriod);
        
        $maturedCount = 0;
        
        AffiliateConversion::query()
            ->where('status', ConversionStatus::Pending)
            ->where('occurred_at', '<=', $cutoffDate)
            ->whereDoesntHave('refund')
            ->chunkById(100, function ($conversions) use (&$maturedCount) {
                foreach ($conversions as $conversion) {
                    $this->matureConversion($conversion);
                    $maturedCount++;
                }
            });
        
        return $maturedCount;
    }
}
```

---

## Database Schema (Proposed Additions)

```php
// affiliate_payout_batches table
Schema::create('affiliate_payout_batches', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('reference')->unique();
    $table->string('status');
    $table->integer('total_payouts')->default(0);
    $table->bigInteger('total_amount_minor')->default(0);
    $table->integer('successful_count')->default(0);
    $table->integer('failed_count')->default(0);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->json('summary')->nullable();
    $table->timestamps();
});

// affiliate_payout_methods table
Schema::create('affiliate_payout_methods', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('type'); // bank_transfer, paypal, stripe, etc.
    $table->json('details'); // encrypted payment details
    $table->boolean('is_verified')->default(false);
    $table->boolean('is_default')->default(false);
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
});

// affiliate_payout_holds table
Schema::create('affiliate_payout_holds', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('reason');
    $table->text('notes')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->foreignUuid('placed_by')->nullable();
    $table->timestamp('released_at')->nullable();
    $table->timestamps();
});

// affiliate_balances table
Schema::create('affiliate_balances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id')->unique();
    $table->string('currency', 3);
    $table->bigInteger('holding_minor')->default(0);
    $table->bigInteger('available_minor')->default(0);
    $table->bigInteger('lifetime_earnings_minor')->default(0);
    $table->bigInteger('minimum_payout_minor');
    $table->timestamps();
});
```

---

## Scheduled Commands

```php
// Mature commissions daily
$schedule->command('affiliates:mature-commissions')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Process scheduled payouts
$schedule->command('affiliates:process-payouts')
    ->dailyAt('06:00')
    ->when(fn () => app(PayoutScheduler::class)->isPayoutDay())
    ->withoutOverlapping();

// Reconcile pending payouts
$schedule->command('affiliates:reconcile-payouts')
    ->everyFourHours()
    ->withoutOverlapping();
```

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | AffiliatePayout model | 1 day | ✅ Done |
| 2 | AffiliatePayoutEvent model | 0.5 day | ✅ Done |
| 3 | AffiliatePayoutService | 2 days | ✅ Done |
| 4 | Filament PayoutResource | 1 day | ✅ Done |
| 5 | PayoutExportService | 1 day | ✅ Done |
| 6 | AffiliateBalance model | 1 day | ⬜ Todo |
| 7 | PayoutBatch model | 1 day | ⬜ Todo |
| 8 | PayoutMethod model | 1 day | ⬜ Todo |
| 9 | PayoutProcessor factory | 2 days | ⬜ Todo |
| 10 | Stripe Connect processor | 2 days | ⬜ Todo |
| 11 | PayPal processor | 2 days | ⬜ Todo |
| 12 | CommissionMaturityService | 1 day | ⬜ Todo |
| 13 | PayoutHold system | 1 day | ⬜ Todo |
| 14 | TaxDocumentService | 3 days | ⬜ Todo |
| 15 | ReconciliationService | 2 days | ⬜ Todo |
| 16 | Scheduled commands | 1 day | ⬜ Todo |

**Remaining Effort:** ~3 weeks

---

## Navigation

**Previous:** [06-affiliate-portal.md](06-affiliate-portal.md)  
**Next:** [08-dynamic-commissions.md](08-dynamic-commissions.md)
