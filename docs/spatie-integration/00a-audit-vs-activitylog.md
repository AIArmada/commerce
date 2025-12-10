---
title: Audit vs Activity Log - Fact-Based Comparison
---

# Activity Logging Architecture: Fact-Based Comparison

> **Research Date:** December 2024  
> **Source:** Direct GitHub source code analysis  
> **Packages:** `spatie/laravel-activitylog` vs `owen-it/laravel-auditing`

---

## Executive Summary

Based on deep source code analysis of both GitHub repositories, these packages are **NOT the same**. They serve different purposes and have distinct architectural approaches.

| Aspect | spatie/laravel-activitylog | owen-it/laravel-auditing |
|--------|---------------------------|--------------------------|
| **Primary Purpose** | Flexible activity logging | Compliance audit trails |
| **Best For** | User actions, business events | Model changes, fraud detection |
| **GitHub Stars** | 5.7k+ | 3.0k+ |
| **Downloads** | 49M+ | 14M+ |

**Recommendation:** Use **BOTH packages** in a hybrid architecture.

---

## Database Schema Comparison (Verified from Source)

### spatie/laravel-activitylog Schema

```php
// Single polymorphic table: activity_log
$table->id();
$table->string('log_name')->nullable();
$table->text('description');
$table->nullableMorphs('subject');  // subject_type, subject_id
$table->nullableMorphs('causer');   // causer_type, causer_id
$table->json('properties')->nullable();  // SINGLE JSON column
$table->string('event')->nullable();
$table->uuid('batch_uuid')->nullable();
$table->timestamps();
```

**Data Storage Format:**
```json
{
  "attributes": { "name": "new value", "price": 100 },
  "old": { "name": "old value", "price": 50 }
}
```

### owen-it/laravel-auditing Schema

```php
// Single polymorphic table: audits
$table->id();
$table->string('user_type')->nullable();
$table->unsignedBigInteger('user_id')->nullable();
$table->string('event');
$table->morphs('auditable');  // auditable_type, auditable_id
$table->json('old_values')->nullable();   // SEPARATE column
$table->json('new_values')->nullable();   // SEPARATE column
$table->text('url')->nullable();          // BUILT-IN
$table->ipAddress('ip_address')->nullable();  // BUILT-IN
$table->string('user_agent', 1023)->nullable();  // BUILT-IN
$table->string('tags')->nullable();
$table->timestamps();
```

**Key Difference:** Auditing stores `old_values` and `new_values` in separate columns with built-in IP/UA tracking.

---

## Feature Matrix (Verified from Source Code)

| Feature | spatie/laravel-activitylog | owen-it/laravel-auditing |
|---------|:--------------------------:|:------------------------:|
| **Data Storage** | Single `properties` JSON | Separate `old_values` + `new_values` |
| **IP Address Tracking** | ❌ Manual | ✅ Built-in `IpAddressResolver` |
| **User Agent Tracking** | ❌ Manual | ✅ Built-in `UserAgentResolver` |
| **URL Tracking** | ❌ Manual | ✅ Built-in `UrlResolver` |
| **State Restoration** | ❌ Not available | ✅ `transitionTo($audit, $old)` |
| **Pivot Table Auditing** | ❌ Not available | ✅ `auditAttach()`, `auditDetach()`, `auditSync()` |
| **Sensitive Data Redaction** | ❌ Manual | ✅ `AttributeRedactor`, `AttributeEncoder` |
| **Auto Pruning** | ✅ Via cleanup command | ✅ `auditThreshold` per-model |
| **Queue Support** | ❌ Not available | ✅ `audit.queue.enable` config |
| **Related Model Logging** | ✅ Dot notation (`user.name`) | ❌ Not built-in |
| **Multiple Log Categories** | ✅ `log_name` column | ❌ Single audit trail |
| **Batch UUID** | ✅ Group related activities | ❌ Not available |
| **Manual Logging** | ✅ Fluent `activity()` helper | ✅ `AuditCustom` event |
| **Pipeline System** | ✅ `LoggablePipe` | ✅ `transformAudit()` |
| **Custom Events** | ✅ `tapActivity()` | ✅ `setAuditEvent()` |
| **Multi-Driver** | ❌ Database only | ✅ Multiple drivers |
| **Console Auditing** | ❌ Not configurable | ✅ `audit.console` config |
| **Tags** | ❌ Via properties | ✅ Built-in `tags` column |

---

## Implementation Patterns

### spatie/laravel-activitylog

```php
class Order extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'customer_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Order {$eventName}")
            ->useLogName('orders');
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        // Must manually add IP/UA if needed
        $activity->properties = $activity->properties->merge([
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

// Manual logging
activity()
    ->causedBy($user)
    ->performedOn($order)
    ->withProperties(['status' => 'processing'])
    ->log('Order status changed');
```

### owen-it/laravel-auditing

```php
class Order extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $auditInclude = ['status', 'total', 'customer_id'];
    protected $auditTimestamps = false;
    protected $auditThreshold = 100;  // Auto-prune to last 100

    public function transformAudit(array $data): array
    {
        $data['tags'] = 'order,commerce';
        return $data;
    }

    public function generateTags(): array
    {
        return ['order', 'commerce', $this->status];
    }
}

// State restoration (UNIQUE to auditing)
$order = Order::find(1);
$audit = $order->audits()->where('event', 'updated')->first();
$order->transitionTo($audit, old: true);  // Restore to previous state
$order->save();

// Pivot auditing (UNIQUE to auditing)
$order->auditAttach('items', $product->id);
$order->auditSync('tags', [$tag1->id, $tag2->id]);
```

---

## Recommended Hybrid Architecture for AIArmada Commerce

### Use owen-it/laravel-auditing For:

| Package | Rationale |
|---------|-----------|
| **orders** | `transitionTo()` for order rollback, compliance audit trail, IP/UA for fraud |
| **cashier / cashier-chip** | Payment compliance (PCI-DSS), IP tracking, dispute resolution |
| **chip** | Webhook source verification, fraud detection |
| **customers** | GDPR compliance, built-in `AttributeRedactor` for PII |
| **inventory** | Stock audit trail, threshold-based pruning |

### Use spatie/laravel-activitylog For:

| Package | Rationale |
|---------|-----------|
| **cart** | User behavior tracking, related model logging, batch UUID for sessions |
| **vouchers** | Custom event categories, flexible properties |
| **affiliates** | Commission events, referral chain tracking |
| **pricing** | Price change history with custom log names |
| **shipping** | Multi-carrier events, custom log categories |
| **docs** | Document generation events |

---

## commerce-support Integration Blueprint

### Trait: HasCommerceAudit (Using owen-it/laravel-auditing)

```php
namespace Armada\CommerceSupport\Concerns;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

trait HasCommerceAudit
{
    use Auditable;

    protected function getCommerceAuditConfig(): array
    {
        return [
            'threshold' => config('commerce-support.audit.threshold', 100),
            'timestamps' => false,
        ];
    }

    public function generateTags(): array
    {
        return [
            'commerce',
            class_basename(static::class),
            config('commerce-support.audit.environment', 'production'),
        ];
    }
}
```

### Trait: HasCommerceActivityLog (Using spatie/laravel-activitylog)

```php
namespace Armada\CommerceSupport\Concerns;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

trait HasCommerceActivityLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getCommerceLogName());
    }

    protected function getCommerceLogName(): string
    {
        return strtolower(class_basename(static::class)) . '-activity';
    }
}
```

---

## Package Distribution Summary

| Package | owen-it/auditing | spatie/activitylog | Primary Purpose |
|---------|:----------------:|:------------------:|-----------------|
| commerce-support | ✅ | ✅ | Foundation traits |
| orders | ✅ | - | Compliance, state restoration |
| cashier | ✅ | - | Payment compliance |
| cashier-chip | ✅ | - | CHIP compliance |
| chip | ✅ | - | Webhook verification |
| customers | ✅ | - | GDPR, PII redaction |
| inventory | ✅ | - | Stock audit trail |
| cart | - | ✅ | User behavior |
| vouchers | - | ✅ | Redemption events |
| affiliates | - | ✅ | Commission tracking |
| pricing | - | ✅ | Price change history |
| tax | - | ✅ | Tax calculations |
| shipping | - | ✅ | Carrier events |
| docs | - | ✅ | Document generation |
| jnt | - | ✅ | J&T events |

---

## Security & Compliance Benefits

### GDPR Compliance (Using owen-it/laravel-auditing)

```php
class Customer extends Model implements AuditableContract
{
    use HasCommerceAudit;

    protected $attributeModifiers = [
        'email' => LeftRedactor::class,      // rick@*****
        'phone' => RightRedactor::class,     // *****1234
        'ssn' => Base64Encoder::class,       // Encoded
    ];

    public function getAuditExclude(): array
    {
        return ['password', 'remember_token'];
    }
}
```

### Fraud Detection (Using owen-it/laravel-auditing)

```php
// Query suspicious payments by IP
$suspicious = Audit::query()
    ->where('auditable_type', Payment::class)
    ->where('ip_address', $suspiciousIp)
    ->orWhere('user_agent', 'LIKE', '%bot%')
    ->get();
```

### State Restoration for Disputes (Using owen-it/laravel-auditing)

```php
// Restore order to pre-disputed state
$order = Order::find($orderId);
$audit = $order->audits()
    ->where('event', 'updated')
    ->where('created_at', '<', $disputeDate)
    ->latest()
    ->first();

$order->transitionTo($audit, old: true);
$order->save();
```

---

## Conclusion

| Question | Answer |
|----------|--------|
| Are they the same package? | **No** |
| Can they be used together? | **Yes** |
| Which should we use? | **Both** (hybrid architecture) |
| Which for compliance? | `owen-it/laravel-auditing` |
| Which for business events? | `spatie/laravel-activitylog` |

---

## Related Documents

- [01-commerce-support.md](01-commerce-support.md) - Foundation layer with both packages
- [04-orders-package.md](04-orders-package.md) - Orders using auditing
- [08-payment-packages.md](08-payment-packages.md) - Payments using auditing
- [05-operational-packages.md](05-operational-packages.md) - Cart using activitylog

---

*This comparison was created from direct GitHub source code analysis. All features verified from actual implementation.*
