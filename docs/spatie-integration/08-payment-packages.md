# Payment & Webhook Packages: Spatie Integration Blueprint

> **Packages:** `aiarmada/cashier`, `aiarmada/cashier-chip`, `aiarmada/chip`  
> **Status:** Built (Enhanceable)  
> **Role:** Extension Layer - Payments

> **Historical note:** The baseline analysis below describes the pre-unification state that motivated this blueprint. CHIP webhook storage, retry handling, and audit coverage are now implemented; use this document as an architectural pattern for remaining payment packages, and check `docs/spatie-integration/PROGRESS.md` for current rollout status.

---

## 📋 Historical Baseline Analysis

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

### Why This was the #1 Priority

When this blueprint was written, payment packages handled webhooks separately:
- Each package had custom webhook handling
- Signature verification was duplicated
- Centralized webhook storage was missing
- Retry mechanisms were inconsistent or missing
- Audit coverage was incomplete

**Current direction:** CHIP already uses unified webhook handling via `spatie/laravel-webhook-client`, and the blueprint below remains the recommended pattern for the rest of the payment stack.

---

### Integration Blueprint: CHIP Webhooks

#### Step 1: Webhook Configuration

```php
// config/webhook-client.php

return [
    'configs' => [
        [
            'name' => 'chip.webhook',
            'signing_secret' => '', // CHIP uses public-key verification in the validator.
            'signature_header_name' => 'X-Signature',
            'signature_validator' => \AIArmada\Chip\Webhooks\ChipSpatieSignatureValidator::class,
            'webhook_profile' => \AIArmada\Chip\Webhooks\ChipWebhookProfile::class,
            'webhook_response' => \AIArmada\Chip\Webhooks\ChipWebhookResponse::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => \AIArmada\Chip\Webhooks\ProcessChipWebhook::class,
            'store_headers' => ['x-signature'],
        ],
    ],
    'delete_after_days' => 90,
];
```

**Webhook Endpoint:** `POST /chip/webhook`

#### Step 2: Custom Signature Validator

```php
// chip/src/Webhooks/ChipSpatieSignatureValidator.php

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Throwable;

final class ChipSpatieSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        /** @var WebhookService $webhookService */
        $webhookService = app(WebhookService::class);

        try {
            return $webhookService->verifySignature($request);
        } catch (Throwable $exception) {
            Log::warning('CHIP webhook signature validation failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
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
    public function shouldProcess(Request $request): bool
    {
        $eventType = $request->input('event_type');

        if (empty($eventType)) {
            return false;
        }

        $validPrefixes = [
            'purchase.',
            'payment.',
            'payout.',
            'billing_template_client.',
        ];

        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($eventType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
```

#### Step 4: Webhook Processor Job

```php
// chip/src/Webhooks/ProcessChipWebhook.php

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;

class ProcessChipWebhook extends CommerceWebhookProcessor
{
    protected function processEvent(string $eventType, array $payload): void
    {
        $dispatcher = app(WebhookEventDispatcher::class);

        WebhookReceived::dispatch(
            $eventType,
            $payload,
            $dispatcher->extractPurchase($payload),
            $dispatcher->extractPayout($payload),
            $dispatcher->extractBillingTemplateClient($payload),
            $dispatcher->extractPayment($payload),
        );

        $dispatcher->dispatch($eventType, $payload);
    }
}
```

#### Step 5: Routes

```php
// chip/routes/webhooks.php

use Illuminate\Support\Facades\Route;
use AIArmada\Chip\Http\Controllers\WebhookController;

Route::post(config('chip.webhooks.route', '/chip/webhook'), [WebhookController::class, 'handle'])
    ->name('chip.webhook');
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
│   POST /chip/webhook     POST /webhooks/stripe    POST /webhooks/{name}     │
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

- [x] Adopt ChipSpatieSignatureValidator
- [x] Create ChipWebhookProfile
- [x] Create ProcessChipWebhook job
- [x] Configure webhook-client for CHIP
- [x] Add webhook routes
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
// CHIP Collect uses RSA public-key verification, not shared-secret HMAC.
// Signature verification should delegate to the package WebhookService.
```

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [01-commerce-support.md](01-commerce-support.md) - Webhook client foundation
- [04-orders-package.md](04-orders-package.md) - Payment state transitions
- [09-shipping-packages.md](09-shipping-packages.md) - Shipping webhooks

---

*This blueprint was created by the Visionary Chief Architect.*
