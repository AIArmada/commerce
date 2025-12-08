# Filament Cashier Vision: Executive Summary

> **Document:** 01 of 05  
> **Package:** `aiarmada/filament-cashier`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Package Hierarchy

```
Payment Gateway APIs
    │
    ├── Stripe API ──────────────────────────────────────────────┐
    │       │                                                    │
    │       └── laravel/cashier          ← Stripe Cashier        │
    │               │                                            │
    │               ├── (optional) filament-stripe-billing       │
    │               │                                            │
    └── CHIP API ───┼────────────────────────────────────────────┤
            │       │                                            │
            └── aiarmada/chip            ← CHIP SDK              │
                    │                                            │
                    └── aiarmada/cashier-chip  ← CHIP Cashier    │
                            │                                    │
                            ├── aiarmada/filament-cashier-chip   │
                            │                                    │
                            └────────────────────────────────────┤
                                                                 │
                    aiarmada/cashier     ← UNIFIED WRAPPER       │
                            │                                    │
                            └── aiarmada/filament-cashier ← THIS │
                                    • Unified Filament Admin     │
                                    • Multi-Gateway Dashboard    │
                                    • Cross-Gateway Analytics    │
                                    • Single Billing Portal      │
                                                                 │
─────────────────────────────────────────────────────────────────┘
```

---

## Purpose Statement

**filament-cashier** provides a **unified Filament admin interface** for managing billing across multiple payment gateways. It serves as the single pane of glass for:

- **Multi-Gateway Subscription Management** - View and manage subscriptions from Stripe and CHIP in one place
- **Unified Revenue Dashboard** - Combined MRR, churn, and analytics across all gateways
- **Cross-Gateway Customer Portal** - Customers manage all their billing in one location
- **Gateway-Agnostic Operations** - Perform actions without knowing which gateway handles each subscription

---

## Strategic Context

### The Multi-Gateway Problem

| Challenge | Current State | With filament-cashier |
|-----------|---------------|----------------------|
| Subscription visibility | Separate admin for each gateway | Single unified view |
| Revenue reporting | Manual aggregation needed | Automatic cross-gateway totals |
| Customer experience | Different portals per gateway | One billing portal |
| Admin training | Learn multiple UIs | Learn one interface |
| Gateway migration | Complex manual process | Built-in migration tools |

### Why Unified Admin Matters

```
Without filament-cashier:
┌─────────────────┐     ┌─────────────────┐
│ Stripe Admin    │     │ Filament CHIP   │
│ (Nova/separate) │     │ (filament-chip) │
└────────┬────────┘     └────────┬────────┘
         │                       │
         └───── Manual ──────────┘
              Aggregation

With filament-cashier:
┌───────────────────────────────────────┐
│         filament-cashier              │
│  ┌─────────────┬─────────────────┐    │
│  │   Stripe    │      CHIP       │    │
│  │ Subscriptions│ Subscriptions  │    │
│  └─────────────┴─────────────────┘    │
│          Unified Dashboard            │
│         Combined Analytics            │
│        Single Customer Portal         │
└───────────────────────────────────────┘
```

---

## Current State Assessment

### aiarmada/cashier Capabilities

| Feature | Status | Notes |
|---------|--------|-------|
| Gateway Manager | ✅ Complete | Factory pattern for resolving gateways |
| Unified Billable Trait | ✅ Complete | Works across gateways |
| Multi-Gateway Subscriptions | ✅ Complete | Query subscriptions from any gateway |
| Gateway-Agnostic Charges | ✅ Complete | Charge via any gateway |
| Cross-Gateway Payment Methods | ✅ Complete | List methods from all gateways |
| Invoice Aggregation | ✅ Complete | Invoices from all gateways |
| Custom Gateway Extension | ✅ Complete | Extend with new gateways |

### Missing Admin UI (This Package Fills)

| Gap | Priority | Impact |
|-----|----------|--------|
| No unified subscription admin | 🔴 Critical | Admins use separate tools |
| No combined revenue dashboard | 🔴 Critical | No cross-gateway visibility |
| No multi-gateway customer portal | 🟡 High | Fragmented customer experience |
| No gateway comparison analytics | 🟡 High | Can't optimize gateway selection |
| No subscription migration UI | 🟢 Medium | Manual migration process |

---

## Vision Pillars

### 1. Unified Subscription Management
Single Filament resource showing ALL subscriptions regardless of gateway, with gateway-aware actions.

### 2. Multi-Gateway Revenue Dashboard
Combined analytics showing total MRR, subscriber counts, and churn across all gateways, with gateway breakdown.

### 3. Cross-Gateway Customer Portal
Customers manage subscriptions, payment methods, and invoices from all their gateways in one place.

### 4. Gateway Intelligence
Tools for comparing gateway performance, optimizing gateway selection, and migrating subscriptions between gateways.

### 5. Seamless Delegation
Complex operations automatically delegate to the correct gateway-specific package without admin awareness.

---

## Technical Architecture

### Plugin Design

```php
use AIArmada\FilamentCashier\FilamentCashierPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCashierPlugin::make()
                ->navigationGroup('Billing')
                ->enableDashboard()
                ->enableSubscriptions()
                ->enableCustomerPortal()
                ->enableGatewayManagement(),
        ]);
}
```

### Gateway Detection

```php
class FilamentCashierPlugin implements Plugin
{
    public function register(Panel $panel): void
    {
        $gateways = $this->detectAvailableGateways();
        
        // Register resources based on available gateways
        if ($gateways->isNotEmpty()) {
            $panel->resources([
                UnifiedSubscriptionResource::class,
                UnifiedInvoiceResource::class,
            ]);
        }
        
        // Register gateway-specific enhancements
        if ($gateways->contains('stripe')) {
            $this->registerStripeEnhancements($panel);
        }
        
        if ($gateways->contains('chip')) {
            $this->registerChipEnhancements($panel);
        }
    }
    
    protected function detectAvailableGateways(): Collection
    {
        return collect([
            'stripe' => class_exists(\Laravel\Cashier\Cashier::class),
            'chip' => class_exists(\AIArmada\CashierChip\Cashier::class),
        ])->filter()->keys();
    }
}
```

### Resource Structure

```
src/
├── FilamentCashierPlugin.php
├── FilamentCashierServiceProvider.php
├── Resources/
│   ├── UnifiedSubscriptionResource/
│   │   ├── UnifiedSubscriptionResource.php
│   │   ├── Pages/
│   │   │   ├── ListSubscriptions.php
│   │   │   ├── ViewSubscription.php
│   │   │   └── CreateSubscription.php
│   │   └── Actions/
│   │       ├── CancelAction.php
│   │       ├── ResumeAction.php
│   │       └── SwitchGatewayAction.php
│   ├── UnifiedCustomerResource/
│   ├── UnifiedInvoiceResource/
│   └── GatewayResource/
├── Widgets/
│   ├── TotalMrrWidget.php
│   ├── GatewayBreakdownWidget.php
│   ├── UnifiedChurnWidget.php
│   └── GatewayComparisonWidget.php
├── Pages/
│   ├── BillingDashboard.php
│   └── GatewayManagement.php
└── CustomerPortal/
    ├── PortalDashboard.php
    ├── AllSubscriptions.php
    └── AllPaymentMethods.php
```

---

## Strategic Impact Matrix

| Feature | Business Value | Technical Complexity | Priority |
|---------|---------------|---------------------|----------|
| Unified Subscription Resource | 🔴 Critical | Medium | P0 |
| Multi-Gateway Dashboard | 🔴 Critical | Medium | P0 |
| Cross-Gateway Customer Portal | 🟡 High | Medium | P1 |
| Gateway Comparison Analytics | 🟡 High | Low | P1 |
| Subscription Migration Tool | 🟢 Medium | High | P2 |
| Gateway Health Monitoring | 🟢 Medium | Low | P2 |

---

## Vision Documents

| # | Document | Description |
|---|----------|-------------|
| 01 | Executive Summary | This document |
| 02 | [Unified Subscriptions](02-unified-subscriptions.md) | Multi-gateway subscription resource |
| 03 | [Multi-Gateway Dashboard](03-multi-gateway-dashboard.md) | Combined analytics and widgets |
| 04 | [Customer Portal](04-customer-portal.md) | Cross-gateway self-service |
| 05 | [Implementation Roadmap](05-implementation-roadmap.md) | Phased delivery plan |

---

## Key Constraints

1. **Abstraction Layer Only** - No direct gateway API calls, all via `aiarmada/cashier`
2. **Gateway Independence** - Works if only one gateway installed
3. **Graceful Degradation** - Features disable if gateway not available
4. **Delegation Pattern** - Complex ops delegate to gateway-specific packages
5. **No Data Duplication** - Uses existing gateway tables

---

## Dependencies

### Required
- `aiarmada/cashier` - Unified billing wrapper
- `filament/filament` ^5.0 - Admin framework

### Optional (Enable Gateway Features)
- `laravel/cashier` - Enables Stripe gateway features
- `aiarmada/cashier-chip` - Enables CHIP gateway features
- `aiarmada/filament-cashier-chip` - Enhanced CHIP-specific UI

---

## Success Criteria

- [ ] Single resource shows subscriptions from ALL gateways
- [ ] Dashboard shows combined MRR across gateways
- [ ] Customer portal works with any gateway combination
- [ ] Gateway-specific actions delegate correctly
- [ ] Works with only one gateway installed
- [ ] PHPStan Level 6 compliance
- [ ] ≥85% test coverage
- [ ] Full translation support (en, ms)

---

## Navigation

**Next:** [02-unified-subscriptions.md](02-unified-subscriptions.md)
