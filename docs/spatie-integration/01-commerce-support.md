# Commerce-Support: Spatie Integration Blueprint

> **Package:** `aiarmada/commerce-support`  
> **Role:** Foundation Layer  
> **Status:** Strategic Enhancement Vision

---

## ⚠️ Critical Update: Hybrid Architecture

**Based on [fact-based GitHub source code research](00a-audit-vs-activitylog.md)**, this package now implements a **hybrid architecture** using BOTH:

| Package | Purpose | Used For |
|---------|---------|----------|
| `owen-it/laravel-auditing` | Compliance audit trails | Orders, Payments, Customers, Inventory |
| `spatie/laravel-activitylog` | Business event logging | Cart, Vouchers, Affiliates, Pricing |

See [00a-audit-vs-activitylog.md](00a-audit-vs-activitylog.md) for the full comparison.

---

## 📋 Current State Analysis

### Dependencies (composer.json)

```json
{
    "require": {
        "php": "^8.4",
        "akaunting/laravel-money": "^6.0",
        "illuminate/support": "^12.0",
        "spatie/laravel-data": "^4.0",          // ✅ Already integrated
        "spatie/laravel-package-tools": "^1.92", // ✅ Already integrated
        "lorisleiva/laravel-actions": "^2.9"
    }
}
```

### Current Structure

```
commerce-support/src/
├── Commands/
├── Contracts/           # Core interfaces (BuyableInterface, etc.)
├── Exceptions/          # Commerce exception hierarchy
├── Traits/             # Shared traits
├── SupportServiceProvider.php
└── helpers.php
```

---

## 🎯 Recommended Additions: Hybrid Audit Architecture

### Priority 1A: owen-it/laravel-auditing (CRITICAL - Compliance)

**Why Critical:** Provides compliance-grade audit trail with state restoration, IP tracking, and PII redaction for regulatory requirements (PCI-DSS, GDPR, SOC2).

```json
{
    "require": {
        "owen-it/laravel-auditing": "^14.0"
    }
}
```

#### Key Features (Verified from Source)

| Feature | Capability |
|---------|------------|
| Separate old/new values | `old_values` + `new_values` columns |
| IP Address Tracking | Built-in `IpAddressResolver` |
| User Agent Tracking | Built-in `UserAgentResolver` |
| URL Tracking | Built-in `UrlResolver` |
| State Restoration | `transitionTo($audit, $old)` |
| Pivot Auditing | `auditAttach()`, `auditDetach()`, `auditSync()` |
| PII Redaction | `AttributeRedactor`, `AttributeEncoder` |
| Queue Support | `audit.queue.enable` config |
| Auto Pruning | `auditThreshold` per-model |

#### Integration Blueprint

```php
// commerce-support/src/Concerns/HasCommerceAudit.php

namespace AIArmada\CommerceSupport\Concerns;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

trait HasCommerceAudit
{
    use Auditable;

    /**
     * Attributes to include in audit.
     * Override in child models.
     */
    protected $auditInclude = [];

    /**
     * Attributes to exclude from audit.
     * Override in child models.
     */
    protected $auditExclude = ['password', 'remember_token'];

    /**
     * Don't audit timestamps.
     */
    protected $auditTimestamps = false;

    /**
     * Auto-prune to last N audits.
     * Override in child models.
     */
    protected $auditThreshold = 100;

    /**
     * Generate tags for the audit.
     */
    public function generateTags(): array
    {
        return [
            'commerce',
            strtolower(class_basename(static::class)),
            config('commerce-support.audit.environment', 'production'),
        ];
    }

    /**
     * Transform audit data before storage.
     */
    public function transformAudit(array $data): array
    {
        // Add commerce-specific metadata
        $data['tags'] = implode(',', $this->generateTags());
        
        return $data;
    }

    /**
     * Restore model to a previous audit state.
     */
    public function restoreToAudit(\OwenIt\Auditing\Models\Audit $audit): bool
    {
        $this->transitionTo($audit, old: true);
        return $this->save();
    }
}
```

#### Compliance Attribute Redaction

```php
// commerce-support/src/Concerns/HasSensitiveAudit.php

namespace AIArmada\CommerceSupport\Concerns;

use OwenIt\Auditing\Redactors\LeftRedactor;
use OwenIt\Auditing\Redactors\RightRedactor;

trait HasSensitiveAudit
{
    use HasCommerceAudit;

    /**
     * Attribute modifiers for sensitive data.
     * email@domain.com → emai*****@domain.com
     * 60123456789 → *****56789
     */
    protected $attributeModifiers = [
        'email' => LeftRedactor::class,
        'phone' => RightRedactor::class,
        'ic_number' => RightRedactor::class,
    ];
}
```

#### Usage in Compliance-Critical Models

```php
// In orders/src/Models/Order.php
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Order extends Model implements AuditableContract
{
    use HasCommerceAudit;

    protected $auditInclude = ['status', 'total', 'customer_id', 'paid_at'];
    protected $auditThreshold = 200;  // Keep more history for orders

    // State restoration for disputes
    public function rollbackToBeforeDispute(\Carbon\Carbon $disputeDate): bool
    {
        $audit = $this->audits()
            ->where('event', 'updated')
            ->where('created_at', '<', $disputeDate)
            ->latest()
            ->first();

        if ($audit) {
            return $this->restoreToAudit($audit);
        }

        return false;
    }
}
```

```php
// In customers/src/Models/Customer.php (GDPR-compliant)
use AIArmada\CommerceSupport\Concerns\HasSensitiveAudit;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Customer extends Model implements AuditableContract
{
    use HasSensitiveAudit;

    protected $auditInclude = ['email', 'phone', 'name', 'status'];
    
    // Automatically redacts email/phone in audit logs
}
```

#### Value Delivered

- ✅ Built-in IP/UA/URL tracking for fraud detection
- ✅ State restoration for dispute resolution
- ✅ GDPR-compliant PII redaction
- ✅ Separate old/new columns for compliance queries
- ✅ Queue support for high-volume auditing
- ✅ Pivot table auditing for relationships

---

### Priority 1B: spatie/laravel-activitylog (CRITICAL - Business Events)

**Why Critical:** Provides flexible activity logging for user actions and business events with batch UUID grouping.

```json
{
    "require": {
        "spatie/laravel-activitylog": "^4.10"
    }
}
```

#### Key Features (Verified from Source)

| Feature | Capability |
|---------|------------|
| Batch UUID | Group related activities in sessions |
| Multiple Log Names | Categorize by `log_name` column |
| Related Model Logging | Dot notation (`user.name`) |
| Fluent API | `activity()->causedBy()->performedOn()->log()` |
| Custom Properties | Flexible JSON properties |
| Tap Activity | `tapActivity()` for customization |

#### Integration Blueprint

```php
// commerce-support/src/Concerns/HasCommerceActivityLog.php

namespace AIArmada\CommerceSupport\Concerns;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

trait HasCommerceActivityLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getLoggableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => $this->getActivityDescription($eventName))
            ->useLogName($this->getActivityLogName());
    }

    protected function getLoggableAttributes(): array
    {
        return ['*']; // Override in child models
    }

    protected function getActivityLogName(): string
    {
        return 'commerce';
    }

    protected function getActivityDescription(string $eventName): string
    {
        $modelName = class_basename($this);
        return match($eventName) {
            'created' => "{$modelName} created",
            'updated' => "{$modelName} updated", 
            'deleted' => "{$modelName} deleted",
            default => "{$modelName} {$eventName}",
        };
    }
}
```

#### Usage in Business Event Models

```php
// In cart/src/Models/Cart.php
use AIArmada\CommerceSupport\Concerns\HasCommerceActivityLog;

class Cart extends Model
{
    use HasCommerceActivityLog;

    protected function getLoggableAttributes(): array
    {
        return ['customer_id', 'total', 'item_count'];
    }

    protected function getActivityLogName(): string
    {
        return 'cart';
    }
}

// In vouchers/src/Models/Voucher.php
class Voucher extends Model
{
    use HasCommerceActivityLog;

    protected function getActivityLogName(): string
    {
        return 'vouchers';
    }
}
```

#### Value Delivered

- ✅ User behavior tracking for analytics
- ✅ Session grouping with batch UUID
- ✅ Flexible custom properties
- ✅ Multiple log categories
- ✅ Related model logging
- ✅ Simple fluent API

---

## 📊 Package Distribution Matrix

| Package | owen-it/auditing | spatie/activitylog | Rationale |
|---------|:----------------:|:------------------:|-----------|
| **commerce-support** | ✅ | ✅ | Foundation provides both traits |
| **orders** | ✅ | - | Compliance, state restoration |
| **cashier** | ✅ | - | PCI-DSS compliance |
| **cashier-chip** | ✅ | - | Payment compliance |
| **chip** | ✅ | - | Webhook source verification |
| **customers** | ✅ | - | GDPR, PII redaction |
| **inventory** | ✅ | - | Stock audit trail |
| **cart** | - | ✅ | User behavior tracking |
| **vouchers** | - | ✅ | Redemption events |
| **affiliates** | - | ✅ | Commission tracking |
| **pricing** | - | ✅ | Price change history |
| **tax** | - | ✅ | Tax calculations |
| **shipping** | - | ✅ | Carrier events |
| **jnt** | - | ✅ | J&T events |
| **docs** | - | ✅ | Document generation |
| **products** | ✅ | ✅ | Both: inventory audit + catalog events |

---

### Priority 2: laravel-webhook-client (HIGH)

**Why High:** Unifies webhook handling across chip, jnt, stripe, and any future integrations.

```json
{
    "require": {
        "spatie/laravel-webhook-client": "^3.4"
    }
}
```

#### Integration Blueprint

```php
// commerce-support/src/Webhooks/CommerceWebhookProfile.php

namespace AIArmada\CommerceSupport\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class CommerceWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        // Only process webhooks with valid commerce event types
        $validEvents = config('commerce-support.webhook_events', []);
        $eventType = $request->header('X-Event-Type') ?? $request->input('event');
        
        return in_array($eventType, $validEvents);
    }
}
```

```php
// commerce-support/src/Webhooks/BaseWebhookProcessor.php

namespace AIArmada\CommerceSupport\Webhooks;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

abstract class BaseWebhookProcessor extends ProcessWebhookJob
{
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $eventType = $payload['event'] ?? 'unknown';

        // Log incoming webhook
        activity()
            ->performedOn($this->webhookCall)
            ->withProperties(['event' => $eventType, 'payload_size' => strlen(json_encode($payload))])
            ->log("Webhook received: {$eventType}");

        $this->processWebhook($this->webhookCall, $eventType, $payload);
    }

    abstract protected function processWebhook(WebhookCall $webhookCall, string $eventType, array $payload): void;
}
```

#### Usage in chip Package

```php
// chip/src/Webhooks/ChipWebhookProcessor.php

use AIArmada\CommerceSupport\Webhooks\BaseWebhookProcessor;

class ChipWebhookProcessor extends BaseWebhookProcessor
{
    protected function processWebhook(WebhookCall $webhookCall, string $eventType, array $payload): void
    {
        match($eventType) {
            'payment.completed' => $this->handlePaymentCompleted($payload),
            'payment.failed' => $this->handlePaymentFailed($payload),
            'refund.processed' => $this->handleRefundProcessed($payload),
            default => $this->handleUnknownEvent($eventType, $payload),
        };
    }
}
```

#### Value Delivered

- ✅ Consistent webhook signature verification
- ✅ Automatic webhook storage in database
- ✅ Built-in retry mechanism for failed webhooks
- ✅ Audit trail for all incoming webhooks
- ✅ Easy to add new webhook sources

---

### Priority 3: laravel-health (MEDIUM)

**Why Medium:** Provides operational visibility into commerce system health.

```json
{
    "require": {
        "spatie/laravel-health": "^1.34"
    }
}
```

#### Integration Blueprint

```php
// commerce-support/src/Health/CommerceHealthServiceProvider.php

namespace AIArmada\CommerceSupport\Health;

use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\QueueCheck;

class CommerceHealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Health::checks([
            DatabaseCheck::new()->name('Commerce Database'),
            CacheCheck::new()->name('Commerce Cache'),
            QueueCheck::new()
                ->onQueue('commerce')
                ->failWhenFailingJobsIsHigherThan(10)
                ->name('Commerce Queue'),
        ]);
    }
}
```

```php
// commerce-support/src/Health/Checks/PaymentGatewayCheck.php

namespace AIArmada\CommerceSupport\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PaymentGatewayCheck extends Check
{
    protected string $gateway;

    public function gateway(string $gateway): self
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();
        
        try {
            $isHealthy = app("commerce.gateway.{$this->gateway}")->healthCheck();
            
            if ($isHealthy) {
                return $result->ok("Gateway {$this->gateway} is operational");
            }
            
            return $result->failed("Gateway {$this->gateway} is not responding");
        } catch (\Exception $e) {
            return $result->failed("Gateway {$this->gateway} error: {$e->getMessage()}");
        }
    }
}
```

#### Usage Example

```php
// In chip package
use AIArmada\CommerceSupport\Health\Checks\PaymentGatewayCheck;

Health::checks([
    PaymentGatewayCheck::new()->gateway('chip')->name('CHIP Gateway'),
]);

// In jnt package
use AIArmada\CommerceSupport\Health\Checks\ShippingCarrierCheck;

Health::checks([
    ShippingCarrierCheck::new()->carrier('jnt')->name('J&T Express API'),
]);
```

#### Value Delivered

- ✅ Real-time health dashboard
- ✅ Payment gateway monitoring
- ✅ Shipping carrier API monitoring
- ✅ Queue health monitoring
- ✅ Proactive alerting capabilities

---

### Priority 4: laravel-settings (MEDIUM)

**Why Medium:** Provides typed, persistent settings for commerce configuration.

```json
{
    "require": {
        "spatie/laravel-settings": "^3.3"
    }
}
```

#### Integration Blueprint

```php
// commerce-support/src/Settings/CommerceSettings.php

namespace AIArmada\CommerceSupport\Settings;

use Spatie\LaravelSettings\Settings;

class CommerceSettings extends Settings
{
    public string $default_currency;
    public string $default_tax_rate;
    public bool $enable_guest_checkout;
    public int $cart_session_lifetime;
    public array $enabled_payment_gateways;
    public array $enabled_shipping_carriers;

    public static function group(): string
    {
        return 'commerce';
    }
}
```

```php
// commerce-support/src/Settings/InventorySettings.php

namespace AIArmada\CommerceSupport\Settings;

use Spatie\LaravelSettings\Settings;

class InventorySettings extends Settings
{
    public bool $track_inventory;
    public bool $allow_backorders;
    public int $low_stock_threshold;
    public string $default_allocation_strategy;
    public bool $reserve_on_cart_add;

    public static function group(): string
    {
        return 'inventory';
    }
}
```

#### Usage Example

```php
// Access settings anywhere
$currency = app(CommerceSettings::class)->default_currency;

// In Filament admin panel
use Filament\Forms\Components\Toggle;

Toggle::make('enable_guest_checkout')
    ->label('Allow Guest Checkout')
    ->default(app(CommerceSettings::class)->enable_guest_checkout);

// Update settings
app(CommerceSettings::class)->update([
    'default_currency' => 'MYR',
    'enable_guest_checkout' => true,
]);
```

#### Value Delivered

- ✅ Type-safe configuration
- ✅ Database-persisted settings
- ✅ Easy Filament settings pages
- ✅ Version-controlled migrations
- ✅ Runtime configuration changes

---

## 📦 Updated composer.json Blueprint

```json
{
    "name": "aiarmada/commerce-support",
    "description": "Core foundation for all AIArmada Commerce packages.",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "akaunting/laravel-money": "^6.0",
        "illuminate/support": "^12.0",
        "lorisleiva/laravel-actions": "^2.9",
        "spatie/laravel-data": "^4.0",
        "spatie/laravel-package-tools": "^1.92",
        "owen-it/laravel-auditing": "^14.0",
        "spatie/laravel-activitylog": "^4.10",
        "spatie/laravel-webhook-client": "^3.4",
        "spatie/laravel-health": "^1.34",
        "spatie/laravel-settings": "^3.3"
    },
    "suggest": {
        "spatie/laravel-medialibrary": "For product and customer media management",
        "spatie/laravel-model-states": "For order and fulfillment state machines",
        "spatie/laravel-sluggable": "For SEO-friendly URLs",
        "spatie/laravel-tags": "For product and customer tagging",
        "spatie/laravel-translatable": "For multi-language support"
    }
}
```

---

## 🏗️ Proposed Directory Structure

```
commerce-support/src/
├── Commands/
├── Concerns/
│   ├── HasCommerceAudit.php           # NEW: Compliance audit (owen-it)
│   ├── HasSensitiveAudit.php          # NEW: PII redaction (owen-it)
│   ├── HasCommerceActivityLog.php     # NEW: Business events (spatie)
│   └── HasCommerceSettings.php        # NEW: Settings access trait
├── Contracts/
│   ├── BuyableInterface.php
│   ├── InventoryableInterface.php
│   ├── TaxableInterface.php
│   ├── WebhookProcessorInterface.php  # NEW: Webhook contract
│   └── HealthCheckableInterface.php   # NEW: Health check contract
├── Exceptions/
├── Health/
│   ├── Checks/
│   │   ├── PaymentGatewayCheck.php    # NEW
│   │   ├── ShippingCarrierCheck.php   # NEW
│   │   └── InventoryLevelCheck.php    # NEW
│   └── CommerceHealthServiceProvider.php
├── Settings/
│   ├── CommerceSettings.php           # NEW
│   ├── InventorySettings.php          # NEW
│   └── PricingSettings.php            # NEW
├── Traits/
├── Webhooks/
│   ├── CommerceWebhookProfile.php     # NEW
│   ├── BaseWebhookProcessor.php       # NEW
│   └── WebhookSignatureValidator.php  # NEW
├── SupportServiceProvider.php
└── helpers.php
```

---

## 📊 Impact Assessment

### Before Integration

| Aspect | Current State |
|--------|---------------|
| Audit Trail | Manual, inconsistent across packages |
| Webhook Handling | Custom per-package, different patterns |
| Health Monitoring | None |
| Configuration | Config files only, no runtime changes |

### After Integration

| Aspect | Enhanced State |
|--------|----------------|
| Audit Trail | Automatic, consistent, queryable via Spatie |
| Webhook Handling | Unified, verified, stored, replayable |
| Health Monitoring | Real-time dashboard, alerting capable |
| Configuration | Typed, persistent, Filament-ready |

---

## ✅ Implementation Checklist

### Phase 1: Add laravel-activitylog

- [ ] Add dependency to composer.json
- [ ] Create `LogsCommerceActivity` trait
- [ ] Add trait to existing models (Order, Payment, etc.)
- [ ] Configure activity log drivers
- [ ] Add Filament activity timeline component

### Phase 2: Add laravel-webhook-client

- [ ] Add dependency to composer.json
- [ ] Create base webhook processor class
- [ ] Create commerce webhook profile
- [ ] Migrate existing chip webhook handling
- [ ] Migrate existing jnt webhook handling
- [ ] Add webhook replay capability

### Phase 3: Add laravel-health

- [ ] Add dependency to composer.json
- [ ] Create payment gateway health check
- [ ] Create shipping carrier health check
- [ ] Create inventory level health check
- [ ] Configure health endpoints
- [ ] Add Filament health widget

### Phase 4: Add laravel-settings

- [ ] Add dependency to composer.json
- [ ] Create commerce settings classes
- [ ] Create settings migrations
- [ ] Add Filament settings pages
- [ ] Migrate existing config to settings

---

## 📈 Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Audit Coverage | 100% | All commerce models logged |
| Webhook Reliability | 99.9% | Successful processing rate |
| Health Check Coverage | 100% | All integrations monitored |
| Settings Migration | 100% | All config in settings |

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master integration overview
- [08-payment-packages.md](08-payment-packages.md) - Webhook client usage in payments
- [09-shipping-packages.md](09-shipping-packages.md) - Webhook client usage in shipping
- [20-implementation-roadmap.md](20-implementation-roadmap.md) - Full implementation timeline

---

*This blueprint was created by the Visionary Chief Architect.*
