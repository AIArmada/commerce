# Payment & Webhook Packages: Spatie Integration Blueprint

> **Packages:** `aiarmada/cashier`, `aiarmada/cashier-chip`, `aiarmada/chip`  
> **Status:** Built (Enhanceable)  
> **Role:** Extension Layer - Payments

---

## 📋 Current State Analysis

### Cashier Package

- Multi-gateway billing wrapper
- Unified API for different providers
- Subscription management
- Gateway manager pattern

### Cashier-CHIP Package

- CHIP adapter for Cashier
- Subscriptions via CHIP
- Charges and refunds
- Webhook handling

### CHIP Package

- Direct CHIP API integration
- Collect & Send APIs
- Purchase, refund, payout operations
- Webhook verification

---

## 🎯 Critical Integration: laravel-webhook-client

### Why This is the #1 Priority

All payment packages handle webhooks. Currently:
- Each package has custom webhook handling
- Signature verification is duplicated
- No centralized webhook storage
- No retry mechanism
- No audit trail

**Solution:** Unified webhook handling via `spatie/laravel-webhook-client`

---

### Integration Blueprint: CHIP Webhooks

#### Step 1: Webhook Configuration

```php
// config/webhook-client.php

return [
    'configs' => [
        [
            'name' => 'chip',
            'signing_secret' => env('CHIP_WEBHOOK_SECRET'),
            'signature_header_name' => 'X-Signature',
            'signature_validator' => \AIArmada\Chip\Webhooks\ChipSignatureValidator::class,
            'webhook_profile' => \AIArmada\Chip\Webhooks\ChipWebhookProfile::class,
            'webhook_response' => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => \AIArmada\Chip\Webhooks\ProcessChipWebhook::class,
            'store_headers' => ['X-Event-Type', 'X-Webhook-ID'],
        ],
    ],
    'delete_after_days' => 90,
];
```

#### Step 2: Custom Signature Validator

```php
// chip/src/Webhooks/ChipSignatureValidator.php

namespace AIArmada\Chip\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class ChipSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header($config->signatureHeaderName);
        
        if (empty($signature)) {
            return false;
        }

        $payload = $request->getContent();
        $secret = $config->signingSecret;

        // CHIP uses HMAC-SHA256
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computedSignature, $signature);
    }
}
```

#### Step 3: Webhook Profile (Event Filtering)

```php
// chip/src/Webhooks/ChipWebhookProfile.php

namespace AIArmada\Chip\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class ChipWebhookProfile implements WebhookProfile
{
    protected array $supportedEvents = [
        'payment.completed',
        'payment.failed',
        'payment.pending',
        'refund.completed',
        'refund.failed',
        'subscription.created',
        'subscription.canceled',
        'subscription.renewed',
        'payout.completed',
    ];

    public function shouldProcess(Request $request): bool
    {
        $eventType = $request->input('event') ?? $request->header('X-Event-Type');
        
        return in_array($eventType, $this->supportedEvents);
    }
}
```

#### Step 4: Webhook Processor Job

```php
// chip/src/Webhooks/ProcessChipWebhook.php

namespace AIArmada\Chip\Webhooks;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use AIArmada\Chip\Events\PaymentCompleted;
use AIArmada\Chip\Events\PaymentFailed;
use AIArmada\Chip\Events\RefundCompleted;

class ProcessChipWebhook extends ProcessWebhookJob
{
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $eventType = $payload['event'] ?? 'unknown';

        // Log the webhook processing
        activity('webhooks')
            ->performedOn($this->webhookCall)
            ->withProperties([
                'event' => $eventType,
                'gateway' => 'chip',
            ])
            ->log("Processing CHIP webhook: {$eventType}");

        match($eventType) {
            'payment.completed' => $this->handlePaymentCompleted($payload),
            'payment.failed' => $this->handlePaymentFailed($payload),
            'payment.pending' => $this->handlePaymentPending($payload),
            'refund.completed' => $this->handleRefundCompleted($payload),
            'refund.failed' => $this->handleRefundFailed($payload),
            'subscription.created' => $this->handleSubscriptionCreated($payload),
            'subscription.canceled' => $this->handleSubscriptionCanceled($payload),
            'subscription.renewed' => $this->handleSubscriptionRenewed($payload),
            'payout.completed' => $this->handlePayoutCompleted($payload),
            default => $this->handleUnknownEvent($eventType, $payload),
        };

        // Mark as processed
        $this->webhookCall->update([
            'processed_at' => now(),
        ]);
    }

    protected function handlePaymentCompleted(array $payload): void
    {
        $purchaseId = $payload['data']['id'];
        $amount = $payload['data']['amount'];
        $reference = $payload['data']['reference'];

        // Find the related order/payment
        $payment = Payment::where('gateway_reference', $purchaseId)->first();

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'confirmed_at' => now(),
            ]);

            // Trigger order state transition
            if ($payment->order) {
                $payment->order->confirmPayment(
                    transactionId: $purchaseId,
                    gateway: 'chip',
                    metadata: $payload['data'],
                );
            }

            event(new PaymentCompleted($payment, $payload));
        }
    }

    protected function handlePaymentFailed(array $payload): void
    {
        $purchaseId = $payload['data']['id'];
        $reason = $payload['data']['failure_reason'] ?? 'Unknown';

        $payment = Payment::where('gateway_reference', $purchaseId)->first();

        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $reason,
            ]);

            event(new PaymentFailed($payment, $reason, $payload));
        }
    }

    protected function handleRefundCompleted(array $payload): void
    {
        $refundId = $payload['data']['id'];
        $originalPurchaseId = $payload['data']['purchase_id'];

        $refund = Refund::where('gateway_reference', $refundId)->first();

        if ($refund) {
            $refund->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            event(new RefundCompleted($refund, $payload));
        }
    }

    protected function handleUnknownEvent(string $eventType, array $payload): void
    {
        activity('webhooks')
            ->performedOn($this->webhookCall)
            ->withProperties(['event' => $eventType, 'payload' => $payload])
            ->log("Unknown CHIP webhook event: {$eventType}");
    }
}
```

#### Step 5: Routes

```php
// chip/routes/webhooks.php

use Illuminate\Support\Facades\Route;

Route::webhooks('webhooks/chip', 'chip');
```

---

### Integration Blueprint: Stripe Webhooks

If/when Stripe is added to cashier:

```php
// config/webhook-client.php - Additional config

[
    'name' => 'stripe',
    'signing_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'signature_header_name' => 'Stripe-Signature',
    'signature_validator' => \AIArmada\Cashier\Stripe\Webhooks\StripeSignatureValidator::class,
    'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
    'process_webhook_job' => \AIArmada\Cashier\Stripe\Webhooks\ProcessStripeWebhook::class,
],
```

```php
// cashier-stripe/src/Webhooks/StripeSignatureValidator.php

namespace AIArmada\Cashier\Stripe\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Stripe\WebhookSignature;

class StripeSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        try {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $config->signingSecret,
                300 // tolerance in seconds
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

---

## 🎯 Secondary Integration: laravel-activitylog

### Payment Activity Logging

```php
// cashier/src/Models/Payment.php

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Payment extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return [
            'amount',
            'currency',
            'status',
            'gateway',
            'transaction_id',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'payments';
    }
}
```

### Subscription Activity Logging

```php
// cashier/src/Models/Subscription.php

class Subscription extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return [
            'status',
            'plan_id',
            'trial_ends_at',
            'ends_at',
            'canceled_at',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'subscriptions';
    }
}
```

---

## 🎯 Tertiary Integration: laravel-health

### Payment Gateway Health Checks

```php
// chip/src/Health/ChipGatewayCheck.php

namespace AIArmada\Chip\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use AIArmada\Chip\ChipClient;

class ChipGatewayCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            $client = app(ChipClient::class);
            $response = $client->healthCheck();

            if ($response->isHealthy()) {
                return $result->ok('CHIP gateway is operational');
            }

            return $result->warning('CHIP gateway is degraded: ' . $response->getMessage());
        } catch (\Exception $e) {
            return $result->failed('CHIP gateway is down: ' . $e->getMessage());
        }
    }
}
```

```php
// Register health checks in service provider

use Spatie\Health\Facades\Health;
use AIArmada\Chip\Health\ChipGatewayCheck;

Health::checks([
    ChipGatewayCheck::new()->name('CHIP Payment Gateway'),
]);
```

---

## 📊 Webhook Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         UNIFIED WEBHOOK ARCHITECTURE                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   CHIP                        Stripe                      Other Gateway     │
│     │                           │                              │            │
│     ▼                           ▼                              ▼            │
│   POST /webhooks/chip    POST /webhooks/stripe    POST /webhooks/{name}     │
│     │                           │                              │            │
│     └───────────────────────────┼──────────────────────────────┘            │
│                                 │                                            │
│                                 ▼                                            │
│                    ┌────────────────────────┐                               │
│                    │  Signature Validation   │                               │
│                    │  (Per-Gateway Validator)│                               │
│                    └────────────┬───────────┘                               │
│                                 │                                            │
│                                 ▼                                            │
│                    ┌────────────────────────┐                               │
│                    │   Webhook Profile       │                               │
│                    │   (Event Filtering)     │                               │
│                    └────────────┬───────────┘                               │
│                                 │                                            │
│                                 ▼                                            │
│                    ┌────────────────────────┐                               │
│                    │   Store in Database     │                               │
│                    │   (webhook_calls table) │                               │
│                    └────────────┬───────────┘                               │
│                                 │                                            │
│                                 ▼                                            │
│                    ┌────────────────────────┐                               │
│                    │   Queue Job for         │                               │
│                    │   Processing            │                               │
│                    └────────────┬───────────┘                               │
│                                 │                                            │
│                                 ▼                                            │
│                    ┌────────────────────────┐                               │
│                    │   Process Webhook       │                               │
│                    │   (Gateway-specific)    │                               │
│                    └────────────┬───────────┘                               │
│                                 │                                            │
│                    ┌────────────┴───────────┐                               │
│                    │                         │                               │
│                    ▼                         ▼                               │
│           ┌───────────────┐       ┌───────────────┐                         │
│           │ Update Order   │       │ Log Activity  │                         │
│           │ State Machine  │       │ (Audit Trail) │                         │
│           └───────────────┘       └───────────────┘                         │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 📦 composer.json Updates

### chip/composer.json

```json
{
    "name": "aiarmada/chip",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0"
    }
}
```

Note: `spatie/laravel-webhook-client` is in `commerce-support`, so chip inherits it.

### cashier/composer.json

```json
{
    "name": "aiarmada/cashier",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0"
    }
}
```

---

## ✅ Implementation Checklist

### Phase 1: Migrate CHIP Webhooks

- [ ] Create ChipSignatureValidator
- [ ] Create ChipWebhookProfile
- [ ] Create ProcessChipWebhook job
- [ ] Configure webhook-client for CHIP
- [ ] Add webhook routes
- [ ] Test signature verification
- [ ] Test event processing
- [ ] Remove old webhook code

### Phase 2: Add Activity Logging

- [ ] Add LogsCommerceActivity to Payment model
- [ ] Add LogsCommerceActivity to Subscription model
- [ ] Add LogsCommerceActivity to Refund model
- [ ] Create Filament payment history widget

### Phase 3: Add Health Checks

- [ ] Create ChipGatewayCheck
- [ ] Register health checks
- [ ] Add Filament health widget
- [ ] Configure alerting

### Phase 4: Prepare for Additional Gateways

- [ ] Create abstract base webhook processor
- [ ] Document webhook integration pattern
- [ ] Create Stripe adapter (if needed)

---

## 🔐 Security Considerations

### Webhook Security Best Practices

1. **Signature Verification**: Always verify signatures before processing
2. **Idempotency**: Handle duplicate webhooks gracefully
3. **Timing Attacks**: Use `hash_equals()` for signature comparison
4. **Replay Prevention**: Check timestamp headers if available
5. **IP Allowlisting**: Consider restricting webhook IPs in production

```php
// Example: Enhanced signature validator with timing check

class ChipSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header($config->signatureHeaderName);
        $timestamp = $request->header('X-Timestamp');
        
        // Reject webhooks older than 5 minutes
        if ($timestamp && abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $config->signingSecret);

        return hash_equals($computedSignature, $signature);
    }
}
```

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [01-commerce-support.md](01-commerce-support.md) - Webhook client foundation
- [04-orders-package.md](04-orders-package.md) - Payment state transitions
- [09-shipping-packages.md](09-shipping-packages.md) - Shipping webhooks

---

*This blueprint was created by the Visionary Chief Architect.*
