# Pricing & Tax Packages: Spatie Integration Blueprint

> **Packages:** `aiarmada/pricing`, `aiarmada/tax`  
> **Status:** Planned (Vision Only)  
> **Role:** Core Layer - Commerce Calculations

---

## 📋 Current Vision Analysis

### Pricing Package (Planned)

- Dynamic pricing engine
- Price lists & tiered pricing
- Customer-specific pricing
- Promotional pricing
- Currency handling
- Price history

### Tax Package (Planned)

- Zone-based tax calculation
- Tax class management
- Tax exemptions
- VAT/GST support
- Tax reports

---

## 🎯 Critical Integration: laravel-settings

### Why Settings for Pricing/Tax

Pricing and tax configurations are:
- Frequently changed by business users
- Need runtime modification without deployment
- Require typed access in code
- Should be auditable

**spatie/laravel-settings** provides type-safe, persistent settings.

---

### Integration Blueprint: Tax Settings

```php
// tax/src/Settings/TaxSettings.php

namespace AIArmada\Tax\Settings;

use Spatie\LaravelSettings\Settings;

class TaxSettings extends Settings
{
    // Default tax behavior
    public bool $pricesIncludeTax;
    public string $defaultTaxClass;
    public bool $calculateTaxOnShipping;
    public bool $roundAtSubtotal;

    // Display settings
    public string $priceDisplayMode; // 'including_tax', 'excluding_tax', 'both'
    public string $cartDisplayMode;
    public string $checkoutDisplayMode;

    // Exemptions
    public bool $allowTaxExemption;
    public array $exemptionDocumentRequired;

    public static function group(): string
    {
        return 'tax';
    }

    public static function encrypted(): array
    {
        return [];
    }
}
```

```php
// tax/src/Settings/TaxZoneSettings.php

namespace AIArmada\Tax\Settings;

use Spatie\LaravelSettings\Settings;

class TaxZoneSettings extends Settings
{
    // Default zone configuration
    public string $defaultZoneId;
    public bool $useCustomerAddress;
    public string $addressPriority; // 'shipping', 'billing'

    // Fallback behavior
    public string $unknownZoneBehavior; // 'default', 'zero', 'error'
    public ?string $fallbackZoneId;

    public static function group(): string
    {
        return 'tax_zones';
    }
}
```

### Settings Migration

```php
// tax/database/settings/create_tax_settings.php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

class CreateTaxSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tax.pricesIncludeTax', false);
        $this->migrator->add('tax.defaultTaxClass', 'standard');
        $this->migrator->add('tax.calculateTaxOnShipping', true);
        $this->migrator->add('tax.roundAtSubtotal', true);
        $this->migrator->add('tax.priceDisplayMode', 'excluding_tax');
        $this->migrator->add('tax.cartDisplayMode', 'excluding_tax');
        $this->migrator->add('tax.checkoutDisplayMode', 'both');
        $this->migrator->add('tax.allowTaxExemption', true);
        $this->migrator->add('tax.exemptionDocumentRequired', ['business_license']);
    }
}
```

### Using Settings in Code

```php
// tax/src/Services/TaxCalculator.php

namespace AIArmada\Tax\Services;

use AIArmada\Tax\Settings\TaxSettings;
use AIArmada\Tax\Settings\TaxZoneSettings;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;
use Akaunting\Money\Money;

class TaxCalculator
{
    public function __construct(
        protected TaxSettings $settings,
        protected TaxZoneSettings $zoneSettings,
    ) {}

    public function calculateTax(Money $amount, string $taxClass, ?string $zoneId = null): TaxResult
    {
        $zone = $this->resolveZone($zoneId);
        $rate = $this->getRate($taxClass, $zone);

        $taxAmount = $this->settings->pricesIncludeTax
            ? $this->extractTax($amount, $rate)
            : $this->addTax($amount, $rate);

        if ($this->settings->roundAtSubtotal) {
            $taxAmount = $this->roundToSubtotal($taxAmount);
        }

        return new TaxResult(
            amount: $taxAmount,
            rate: $rate,
            zone: $zone,
            includedInPrice: $this->settings->pricesIncludeTax,
        );
    }

    protected function resolveZone(?string $zoneId): TaxZone
    {
        if ($zoneId) {
            return TaxZone::findOrFail($zoneId);
        }

        // Use default zone
        $defaultZoneId = $this->zoneSettings->defaultZoneId;
        
        if (!$defaultZoneId) {
            return match($this->zoneSettings->unknownZoneBehavior) {
                'zero' => TaxZone::zeroRate(),
                'error' => throw new TaxZoneNotConfiguredException(),
                default => TaxZone::find($this->zoneSettings->fallbackZoneId) 
                    ?? TaxZone::zeroRate(),
            };
        }

        return TaxZone::findOrFail($defaultZoneId);
    }

    protected function getRate(string $taxClass, TaxZone $zone): TaxRate
    {
        return TaxRate::query()
            ->where('tax_class', $taxClass)
            ->where('zone_id', $zone->id)
            ->first() ?? TaxRate::zeroRate($taxClass, $zone);
    }
}
```

---

### Integration Blueprint: Pricing Settings

```php
// pricing/src/Settings/PricingSettings.php

namespace AIArmada\Pricing\Settings;

use Spatie\LaravelSettings\Settings;

class PricingSettings extends Settings
{
    // Currency settings
    public string $baseCurrency;
    public array $enabledCurrencies;
    public string $currencyDisplay; // 'symbol', 'code', 'both'

    // Rounding settings
    public string $roundingMode; // 'up', 'down', 'nearest'
    public int $decimalPlaces;

    // Price display
    public bool $showOriginalPrice;
    public bool $showSavingsAmount;
    public bool $showSavingsPercentage;

    // Bulk pricing
    public bool $enableTieredPricing;
    public bool $showTierBreakdown;

    public static function group(): string
    {
        return 'pricing';
    }
}
```

```php
// pricing/src/Settings/PromotionalPricingSettings.php

namespace AIArmada\Pricing\Settings;

use Spatie\LaravelSettings\Settings;

class PromotionalPricingSettings extends Settings
{
    // Sale pricing
    public bool $enableSalePricing;
    public bool $showSaleCountdown;
    public bool $hideExpiredSales;

    // Member pricing
    public bool $enableMemberPricing;
    public array $memberPriceGroups;

    // Flash sales
    public bool $enableFlashSales;
    public int $maxFlashSaleItems;

    public static function group(): string
    {
        return 'promotional_pricing';
    }
}
```

---

## 🎯 Secondary Integration: laravel-activitylog

### Pricing Activity Logging

Price changes are critical business events that must be audited.

```php
// pricing/src/Models/Price.php

namespace AIArmada\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Price extends Model
{
    use LogsCommerceActivity;

    protected $fillable = [
        'product_id',
        'variant_id',
        'price_list_id',
        'amount',
        'currency',
        'min_quantity',
        'starts_at',
        'ends_at',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'amount',
            'currency',
            'min_quantity',
            'starts_at',
            'ends_at',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'pricing';
    }
}
```

### Tax Rate Activity Logging

```php
// tax/src/Models/TaxRate.php

namespace AIArmada\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class TaxRate extends Model
{
    use LogsCommerceActivity;

    protected $fillable = [
        'zone_id',
        'tax_class',
        'name',
        'rate',
        'is_compound',
        'priority',
    ];

    protected function getLoggableAttributes(): array
    {
        return [
            'name',
            'rate',
            'is_compound',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'tax';
    }
}
```

### Settings Change Audit

```php
// commerce-support/src/Listeners/LogSettingsChange.php

namespace AIArmada\CommerceSupport\Listeners;

use Spatie\LaravelSettings\Events\SettingsSaved;

class LogSettingsChange
{
    public function handle(SettingsSaved $event): void
    {
        $settings = $event->settings;
        $group = $settings::group();
        $changes = $event->changedProperties;

        if (empty($changes)) {
            return;
        }

        $changedData = [];
        foreach ($changes as $property) {
            $changedData[$property] = [
                'old' => $event->originalProperties[$property] ?? null,
                'new' => $settings->$property,
            ];
        }

        activity('settings')
            ->withProperties([
                'group' => $group,
                'changes' => $changedData,
            ])
            ->log("Settings updated: {$group}");
    }
}
```

---

## 🎯 Tertiary Integration: laravel-multitenancy (Future)

For multi-tenant commerce platforms, pricing and tax may vary per tenant.

```php
// pricing/src/Models/Price.php (Multi-tenant version)

use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Price extends Model
{
    use UsesTenantConnection;
    use LogsCommerceActivity;

    // Model now uses tenant-specific database
}
```

```php
// config/multitenancy.php

return [
    'tenant_model' => \App\Models\Store::class,
    
    'actions' => [
        \Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction::class,
        \AIArmada\CommerceSupport\Actions\SwitchTenantSettingsAction::class,
    ],
];
```

---

## 📊 Pricing/Tax Architecture

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                      PRICING & TAX ARCHITECTURE                               │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│   ┌────────────────────────────────────────────────────────────────────┐     │
│   │                    SETTINGS (spatie/laravel-settings)              │     │
│   │                                                                     │     │
│   │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐    │     │
│   │  │  PricingSettings│  │   TaxSettings   │  │ TaxZoneSettings │    │     │
│   │  │                 │  │                 │  │                 │    │     │
│   │  │ - baseCurrency  │  │ - includeTax    │  │ - defaultZone   │    │     │
│   │  │ - rounding      │  │ - displayMode   │  │ - fallback      │    │     │
│   │  │ - display       │  │ - exemptions    │  │ - behavior      │    │     │
│   │  └─────────────────┘  └─────────────────┘  └─────────────────┘    │     │
│   └────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│                                    ▼                                          │
│   ┌────────────────────────────────────────────────────────────────────┐     │
│   │                         CALCULATION ENGINE                          │     │
│   │                                                                     │     │
│   │  ┌─────────────────┐                    ┌─────────────────┐        │     │
│   │  │ PriceCalculator │◄──── Product ────►│  TaxCalculator  │        │     │
│   │  │                 │      Zone          │                 │        │     │
│   │  │ - Base price    │      Customer      │ - Tax rate      │        │     │
│   │  │ - Tier pricing  │                    │ - Zone lookup   │        │     │
│   │  │ - Promotions    │                    │ - Exemptions    │        │     │
│   │  └────────┬────────┘                    └────────┬────────┘        │     │
│   │           │                                      │                  │     │
│   │           └──────────────┬───────────────────────┘                  │     │
│   │                          ▼                                          │     │
│   │                  ┌───────────────┐                                  │     │
│   │                  │ Final Price   │                                  │     │
│   │                  │ (with tax)    │                                  │     │
│   │                  └───────────────┘                                  │     │
│   └────────────────────────────────────────────────────────────────────┘     │
│                                    │                                          │
│                                    ▼                                          │
│   ┌────────────────────────────────────────────────────────────────────┐     │
│   │                    ACTIVITY LOG (Audit Trail)                       │     │
│   │                                                                     │     │
│   │  - Price changes logged with old/new values                        │     │
│   │  - Tax rate changes logged                                         │     │
│   │  - Settings changes logged                                         │     │
│   │  - Exemption grants logged                                         │     │
│   └────────────────────────────────────────────────────────────────────┘     │
│                                                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Price History with Activity Log

```php
// pricing/src/Services/PriceHistoryService.php

namespace AIArmada\Pricing\Services;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use AIArmada\Pricing\Models\Price;

class PriceHistoryService
{
    public function getPriceHistory(string $productId, int $months = 12): Collection
    {
        return Activity::query()
            ->where('log_name', 'pricing')
            ->where('subject_type', Price::class)
            ->whereJsonContains('properties->attributes->product_id', $productId)
            ->where('created_at', '>=', now()->subMonths($months))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Activity $activity) => [
                'date' => $activity->created_at,
                'old_price' => $activity->properties['old']['amount'] ?? null,
                'new_price' => $activity->properties['attributes']['amount'] ?? null,
                'changed_by' => $activity->causer?->name ?? 'System',
            ]);
    }

    public function generatePricingReport(
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): array {
        $activities = Activity::query()
            ->where('log_name', 'pricing')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'total_changes' => $activities->count(),
            'price_increases' => $activities->filter(fn ($a) => 
                ($a->properties['attributes']['amount'] ?? 0) > 
                ($a->properties['old']['amount'] ?? 0)
            )->count(),
            'price_decreases' => $activities->filter(fn ($a) => 
                ($a->properties['attributes']['amount'] ?? 0) < 
                ($a->properties['old']['amount'] ?? 0)
            )->count(),
            'by_user' => $activities->groupBy(fn ($a) => 
                $a->causer?->name ?? 'System'
            )->map->count(),
        ];
    }
}
```

---

## 📦 composer.json Blueprints

### pricing/composer.json

```json
{
    "name": "aiarmada/pricing",
    "description": "Dynamic pricing engine for AIArmada Commerce",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "akaunting/laravel-money": "^6.0",
        "spatie/laravel-settings": "^3.3"
    },
    "autoload": {
        "psr-4": {
            "AIArmada\\Pricing\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "AIArmada\\Pricing\\PricingServiceProvider"
            ]
        }
    }
}
```

### tax/composer.json

```json
{
    "name": "aiarmada/tax",
    "description": "Tax calculation engine for AIArmada Commerce",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "akaunting/laravel-money": "^6.0",
        "spatie/laravel-settings": "^3.3"
    },
    "autoload": {
        "psr-4": {
            "AIArmada\\Tax\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "AIArmada\\Tax\\TaxServiceProvider"
            ]
        }
    }
}
```

---

## ✅ Implementation Checklist

### Phase 1: Settings Infrastructure

- [ ] Add spatie/laravel-settings to commerce-support
- [ ] Create TaxSettings class
- [ ] Create TaxZoneSettings class
- [ ] Create PricingSettings class
- [ ] Create settings migrations
- [ ] Create settings change listener

### Phase 2: Tax Package Core

- [ ] Create TaxZone model with activity logging
- [ ] Create TaxRate model with activity logging
- [ ] Create TaxCalculator service
- [ ] Implement zone resolution logic
- [ ] Write comprehensive tests

### Phase 3: Pricing Package Core

- [ ] Create Price model with activity logging
- [ ] Create PriceList model
- [ ] Create PriceCalculator service
- [ ] Implement tiered pricing
- [ ] Write comprehensive tests

### Phase 4: Audit & Reporting

- [ ] Create PriceHistoryService
- [ ] Create TaxReportService
- [ ] Create Filament widgets for reporting
- [ ] Export price/tax change reports

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [01-commerce-support.md](01-commerce-support.md) - Settings foundation
- [04-orders-package.md](04-orders-package.md) - Tax calculation at checkout
- [05-operational-packages.md](05-operational-packages.md) - Cart tax calculation

---

*This blueprint was created by the Visionary Chief Architect.*
