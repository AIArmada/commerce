# Subscription Management

> **Document:** 02 of 10  
> **Package:** `aiarmada/chip`  
> **Status:** Vision

---

## Overview

Build a comprehensive **subscription management system** that handles recurring billing, plan management, trials, grace periods, prorations, and lifecycle automation—all integrated with Chip's payment infrastructure.

---

## Subscription Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  SUBSCRIPTION SYSTEM                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────┐     ┌───────────┐     ┌───────────┐         │
│  │   Plans   │────►│Subscriptions────►│  Billing  │         │
│  └───────────┘     └───────────┘     │   Cycles  │         │
│                          │            └───────────┘         │
│                          │                   │               │
│                          ▼                   ▼               │
│                    ┌───────────┐     ┌───────────┐         │
│                    │  Invoices │     │  Payments │         │
│                    └───────────┘     └───────────┘         │
│                                                              │
│  Lifecycle: Trial → Active → Past Due → Canceled            │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Subscription Models

### ChipPlan

```php
/**
 * Subscription plan definition
 * 
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_minor
 * @property string $currency
 * @property string $interval (daily, weekly, monthly, yearly)
 * @property int $interval_count
 * @property int|null $trial_days
 * @property array|null $features
 * @property array|null $metadata
 * @property bool $is_active
 * @property Carbon|null $archived_at
 */
class ChipPlan extends Model
{
    use HasUuids;
    
    protected $casts = [
        'interval' => BillingInterval::class,
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
    ];
    
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ChipSubscription::class, 'plan_id');
    }
    
    public function getMonthlyEquivalent(): int
    {
        return match ($this->interval) {
            BillingInterval::Daily => $this->price_minor * 30,
            BillingInterval::Weekly => $this->price_minor * 4,
            BillingInterval::Monthly => $this->price_minor,
            BillingInterval::Yearly => (int) ($this->price_minor / 12),
        };
    }
}

enum BillingInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    
    public function toDays(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
            self::Yearly => 365,
        };
    }
}
```

### ChipSubscription

```php
/**
 * Active subscription instance
 * 
 * @property string $id
 * @property string $plan_id
 * @property string $subscriber_type
 * @property string $subscriber_id
 * @property string|null $chip_customer_id
 * @property string|null $chip_token_id
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon $current_period_start
 * @property Carbon $current_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $ends_at
 * @property int|null $quantity
 * @property array|null $metadata
 */
class ChipSubscription extends Model
{
    use HasUuids;
    
    protected $casts = [
        'status' => SubscriptionStatus::class,
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ChipPlan::class, 'plan_id');
    }
    
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function invoices(): HasMany
    {
        return $this->hasMany(ChipSubscriptionInvoice::class, 'subscription_id');
    }
    
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active 
            || $this->isOnTrial();
    }
    
    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing 
            && $this->trial_ends_at?->isFuture();
    }
    
    public function isOnGracePeriod(): bool
    {
        return $this->canceled_at 
            && $this->ends_at?->isFuture();
    }
    
    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }
}

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Paused = 'paused';
}
```

---

## Subscription Service

### ChipSubscriptionService

```php
class ChipSubscriptionService
{
    public function __construct(
        private ChipClient $chip,
        private SubscriptionInvoicer $invoicer,
        private SubscriptionNotifier $notifier,
    ) {}
    
    /**
     * Create a new subscription
     */
    public function create(
        Model $subscriber,
        ChipPlan $plan,
        ?string $paymentMethodId = null,
        array $options = []
    ): ChipSubscription {
        // Create or retrieve Chip customer
        $customerId = $this->ensureCustomer($subscriber);
        
        // Determine trial period
        $trialDays = $options['trial_days'] ?? $plan->trial_days;
        $trialEndsAt = $trialDays ? now()->addDays($trialDays) : null;
        
        // Calculate billing period
        $periodStart = now();
        $periodEnd = $this->calculatePeriodEnd($plan, $periodStart);
        
        $subscription = ChipSubscription::create([
            'plan_id' => $plan->id,
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->getKey(),
            'chip_customer_id' => $customerId,
            'chip_token_id' => $paymentMethodId,
            'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $periodStart,
            'current_period_end' => $trialEndsAt ?? $periodEnd,
            'quantity' => $options['quantity'] ?? 1,
            'metadata' => $options['metadata'] ?? null,
        ]);
        
        // Bill immediately if no trial
        if (!$trialEndsAt) {
            $this->billSubscription($subscription);
        }
        
        event(new SubscriptionCreated($subscription));
        
        return $subscription;
    }
    
    /**
     * Cancel subscription
     */
    public function cancel(
        ChipSubscription $subscription,
        bool $immediately = false
    ): ChipSubscription {
        $subscription->canceled_at = now();
        
        if ($immediately) {
            $subscription->status = SubscriptionStatus::Canceled;
            $subscription->ends_at = now();
        } else {
            // Cancel at end of current period
            $subscription->ends_at = $subscription->current_period_end;
        }
        
        $subscription->save();
        
        event(new SubscriptionCanceled($subscription, $immediately));
        
        return $subscription;
    }
    
    /**
     * Resume a canceled subscription
     */
    public function resume(ChipSubscription $subscription): ChipSubscription
    {
        if (!$subscription->isOnGracePeriod()) {
            throw new SubscriptionException('Cannot resume expired subscription');
        }
        
        $subscription->update([
            'canceled_at' => null,
            'ends_at' => null,
            'status' => SubscriptionStatus::Active,
        ]);
        
        event(new SubscriptionResumed($subscription));
        
        return $subscription;
    }
    
    /**
     * Change subscription plan
     */
    public function changePlan(
        ChipSubscription $subscription,
        ChipPlan $newPlan,
        bool $prorate = true
    ): ChipSubscription {
        $oldPlan = $subscription->plan;
        
        if ($prorate) {
            $proration = $this->calculateProration($subscription, $newPlan);
            
            if ($proration['credit'] > 0) {
                $this->applyCredit($subscription, $proration['credit']);
            }
            
            if ($proration['charge'] > 0) {
                $this->chargeProration($subscription, $proration['charge']);
            }
        }
        
        $subscription->update(['plan_id' => $newPlan->id]);
        
        event(new SubscriptionPlanChanged($subscription, $oldPlan, $newPlan));
        
        return $subscription;
    }
    
    /**
     * Pause subscription
     */
    public function pause(
        ChipSubscription $subscription,
        ?Carbon $resumeAt = null
    ): ChipSubscription {
        $subscription->update([
            'status' => SubscriptionStatus::Paused,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'paused_at' => now()->toIso8601String(),
                'resume_at' => $resumeAt?->toIso8601String(),
            ]),
        ]);
        
        event(new SubscriptionPaused($subscription));
        
        return $subscription;
    }
}
```

---

## Billing Cycle Management

### SubscriptionBillingService

```php
class SubscriptionBillingService
{
    public function __construct(
        private ChipClient $chip,
        private SubscriptionInvoicer $invoicer,
        private SubscriptionNotifier $notifier,
    ) {}
    
    /**
     * Process all due subscriptions
     */
    public function processDueSubscriptions(): BillingResult
    {
        $result = new BillingResult();
        
        $dueSubscriptions = ChipSubscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
            ])
            ->where('current_period_end', '<=', now())
            ->get();
        
        foreach ($dueSubscriptions as $subscription) {
            try {
                $this->renewSubscription($subscription);
                $result->addSuccess($subscription);
            } catch (PaymentFailedException $e) {
                $this->handlePaymentFailure($subscription, $e);
                $result->addFailure($subscription, $e);
            }
        }
        
        return $result;
    }
    
    /**
     * Renew a single subscription
     */
    public function renewSubscription(ChipSubscription $subscription): ChipSubscriptionInvoice
    {
        // Check if trial ending
        if ($subscription->isOnTrial() && $subscription->trial_ends_at->isPast()) {
            $subscription->update(['status' => SubscriptionStatus::Active]);
        }
        
        // Create invoice
        $invoice = $this->invoicer->createForSubscription($subscription);
        
        // Attempt payment
        $payment = $this->chargeSubscription($subscription, $invoice);
        
        if ($payment->isSuccessful()) {
            $this->advanceBillingPeriod($subscription);
            $invoice->markAsPaid($payment);
            
            event(new SubscriptionRenewed($subscription, $invoice));
        } else {
            throw new PaymentFailedException($payment);
        }
        
        return $invoice;
    }
    
    /**
     * Handle payment failure
     */
    private function handlePaymentFailure(
        ChipSubscription $subscription,
        PaymentFailedException $e
    ): void {
        $subscription->update(['status' => SubscriptionStatus::PastDue]);
        
        // Schedule retry
        $this->scheduleRetry($subscription);
        
        // Notify subscriber
        $this->notifier->paymentFailed($subscription, $e);
        
        event(new SubscriptionPaymentFailed($subscription, $e));
    }
    
    /**
     * Schedule payment retry with exponential backoff
     */
    private function scheduleRetry(ChipSubscription $subscription): void
    {
        $retryCount = $subscription->metadata['retry_count'] ?? 0;
        $maxRetries = config('chip.subscription.max_retries', 3);
        
        if ($retryCount >= $maxRetries) {
            $this->handleFinalFailure($subscription);
            return;
        }
        
        $delay = pow(2, $retryCount) * 24; // 24, 48, 96 hours
        
        RetrySubscriptionPayment::dispatch($subscription)
            ->delay(now()->addHours($delay));
        
        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'retry_count' => $retryCount + 1,
                'next_retry_at' => now()->addHours($delay)->toIso8601String(),
            ]),
        ]);
    }
}
```

---

## Subscription Invoicing

### SubscriptionInvoicer

```php
class SubscriptionInvoicer
{
    public function createForSubscription(ChipSubscription $subscription): ChipSubscriptionInvoice
    {
        $plan = $subscription->plan;
        $quantity = $subscription->quantity ?? 1;
        
        $subtotal = $plan->price_minor * $quantity;
        $tax = $this->calculateTax($subscription, $subtotal);
        $total = $subtotal + $tax;
        
        // Apply any credits
        $credits = $this->getAvailableCredits($subscription);
        $appliedCredit = min($credits, $total);
        $amountDue = $total - $appliedCredit;
        
        return ChipSubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'period_start' => $subscription->current_period_end,
            'period_end' => $this->calculatePeriodEnd($plan, $subscription->current_period_end),
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'credit_applied_minor' => $appliedCredit,
            'total_minor' => $total,
            'amount_due_minor' => $amountDue,
            'currency' => $plan->currency,
            'status' => InvoiceStatus::Open,
            'line_items' => [
                [
                    'description' => "{$plan->name} × {$quantity}",
                    'quantity' => $quantity,
                    'unit_price_minor' => $plan->price_minor,
                    'total_minor' => $subtotal,
                ],
            ],
        ]);
    }
}
```

---

## Usage Tracking (Metered Billing)

### UsageTracker

```php
class UsageTracker
{
    /**
     * Record usage for metered billing
     */
    public function record(
        ChipSubscription $subscription,
        string $metricName,
        int $quantity,
        ?Carbon $timestamp = null
    ): ChipUsageRecord {
        return ChipUsageRecord::create([
            'subscription_id' => $subscription->id,
            'metric_name' => $metricName,
            'quantity' => $quantity,
            'timestamp' => $timestamp ?? now(),
        ]);
    }
    
    /**
     * Get usage summary for billing period
     */
    public function getSummary(
        ChipSubscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        return ChipUsageRecord::query()
            ->where('subscription_id', $subscription->id)
            ->whereBetween('timestamp', [$periodStart, $periodEnd])
            ->selectRaw('metric_name, SUM(quantity) as total')
            ->groupBy('metric_name')
            ->pluck('total', 'metric_name')
            ->toArray();
    }
}
```

---

## Database Schema

```php
// chip_plans table
Schema::create('chip_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->bigInteger('price_minor');
    $table->string('currency', 3)->default('MYR');
    $table->string('interval');
    $table->integer('interval_count')->default(1);
    $table->integer('trial_days')->nullable();
    $table->json('features')->nullable();
    $table->json('metadata')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('archived_at')->nullable();
    $table->timestamps();
});

// chip_subscriptions table
Schema::create('chip_subscriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('plan_id');
    $table->uuidMorphs('subscriber');
    $table->string('chip_customer_id')->nullable();
    $table->string('chip_token_id')->nullable();
    $table->string('status');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start');
    $table->timestamp('current_period_end');
    $table->timestamp('canceled_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->integer('quantity')->default(1);
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['subscriber_type', 'subscriber_id']);
    $table->index(['status', 'current_period_end']);
});

// chip_subscription_invoices table
Schema::create('chip_subscription_invoices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('subscription_id');
    $table->foreignUuid('plan_id');
    $table->timestamp('period_start');
    $table->timestamp('period_end');
    $table->bigInteger('subtotal_minor');
    $table->bigInteger('tax_minor')->default(0);
    $table->bigInteger('credit_applied_minor')->default(0);
    $table->bigInteger('total_minor');
    $table->bigInteger('amount_due_minor');
    $table->string('currency', 3);
    $table->string('status');
    $table->json('line_items');
    $table->string('chip_payment_id')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    
    $table->index(['subscription_id', 'status']);
});

// chip_usage_records table (for metered billing)
Schema::create('chip_usage_records', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('subscription_id');
    $table->string('metric_name');
    $table->integer('quantity');
    $table->timestamp('timestamp');
    $table->timestamps();
    
    $table->index(['subscription_id', 'metric_name', 'timestamp']);
});
```

---

## Scheduled Commands

```php
// Process due subscriptions
$schedule->command('chip:process-subscriptions')
    ->hourly()
    ->withoutOverlapping();

// Expire trial subscriptions
$schedule->command('chip:expire-trials')
    ->daily()
    ->withoutOverlapping();

// Send renewal reminders
$schedule->command('chip:send-renewal-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Cancel past due subscriptions
$schedule->command('chip:cancel-past-due')
    ->daily()
    ->withoutOverlapping();
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-billing-templates.md](03-billing-templates.md)
