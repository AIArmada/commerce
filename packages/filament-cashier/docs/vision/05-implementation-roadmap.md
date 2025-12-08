# Filament Cashier Vision: Implementation Roadmap

> **Document:** 05 of 05  
> **Package:** `aiarmada/filament-cashier`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

This document outlines the phased implementation plan for `aiarmada/filament-cashier`. The package provides a unified Filament admin interface for multi-gateway billing management, building on top of `aiarmada/cashier`.

---

## Timeline Overview

```
Week 1-2: Foundation & Gateway Detection
    │
Week 3-5: Unified Subscription Resource
    │
Week 6-8: Multi-Gateway Dashboard
    │
Week 9-11: Customer Billing Portal
    │
Week 12-13: Invoicing & Reports
    │
Week 14-15: Gateway Management & Advanced Features
    │
Week 16: Polish, Testing & Documentation
```

---

## Phase 1: Foundation & Gateway Detection (Weeks 1-2)

### Objective
Establish package structure, gateway detection, and Filament plugin foundation.

### Deliverables

#### Package Structure
```
packages/filament-cashier/
├── composer.json
├── config/
│   └── filament-cashier.php
├── docs/
│   ├── index.md
│   ├── installation.md
│   └── vision/
├── resources/
│   ├── lang/
│   │   ├── en/
│   │   └── ms/
│   └── views/
│       ├── components/
│       ├── pages/
│       └── widgets/
├── src/
│   ├── FilamentCashierPlugin.php
│   ├── FilamentCashierServiceProvider.php
│   ├── Support/
│   │   ├── GatewayDetector.php
│   │   ├── UnifiedSubscription.php
│   │   └── SubscriptionStatus.php
│   ├── Components/
│   │   └── GatewayBadge.php
│   ├── Resources/
│   ├── Widgets/
│   └── Pages/
└── README.md
```

#### composer.json
```json
{
    "name": "aiarmada/filament-cashier",
    "description": "Unified Filament admin for multi-gateway billing",
    "require": {
        "aiarmada/cashier": "self.version",
        "filament/filament": "^5.0"
    },
    "suggest": {
        "laravel/cashier": "Required for Stripe gateway features",
        "aiarmada/cashier-chip": "Required for CHIP gateway features",
        "aiarmada/filament-cashier-chip": "Enhanced CHIP-specific UI"
    }
}
```

#### Gateway Detection Service

```php
class GatewayDetector
{
    public function availableGateways(): Collection
    {
        return collect([
            'stripe' => class_exists(\Laravel\Cashier\Cashier::class),
            'chip' => class_exists(\AIArmada\CashierChip\Cashier::class),
        ])->filter()->keys();
    }

    public function isAvailable(string $gateway): bool
    {
        return $this->availableGateways()->contains($gateway);
    }

    public function getGatewayConfig(string $gateway): array
    {
        return config("filament-cashier.gateways.{$gateway}", [
            'label' => ucfirst($gateway),
            'icon' => 'heroicon-o-cube',
            'color' => 'gray',
        ]);
    }
}
```

#### Plugin Class

```php
class FilamentCashierPlugin implements Plugin
{
    protected bool $customerPortalMode = false;
    protected bool $enableDashboard = true;
    protected bool $enableSubscriptions = true;
    protected bool $enableGatewayManagement = false;
    protected ?string $navigationGroup = null;

    public function register(Panel $panel): void
    {
        $gateways = app(GatewayDetector::class)->availableGateways();

        if ($gateways->isEmpty()) {
            // No gateways installed - show setup page only
            $panel->pages([GatewaySetupPage::class]);
            return;
        }

        if ($this->enableSubscriptions) {
            $panel->resources([
                UnifiedSubscriptionResource::class,
                UnifiedInvoiceResource::class,
            ]);
        }

        if ($this->enableDashboard && !$this->customerPortalMode) {
            $panel->pages([UnifiedBillingDashboard::class]);
            $panel->widgets([
                TotalMrrWidget::class,
                TotalSubscribersWidget::class,
                GatewayComparisonWidget::class,
            ]);
        }

        if ($this->enableGatewayManagement) {
            $panel->pages([GatewayManagementPage::class]);
        }
    }
}
```

### Checklist
- [ ] Create package directory structure
- [ ] Set up composer.json with dependencies
- [ ] Create service provider
- [ ] Create plugin class with configuration
- [ ] Implement GatewayDetector service
- [ ] Create GatewayBadge component
- [ ] Create UnifiedSubscription DTO
- [ ] Create SubscriptionStatus enum
- [ ] Set up translation files
- [ ] Configuration file with gateway settings

---

## Phase 2: Unified Subscription Resource (Weeks 3-5)

### Objective
Implement the core UnifiedSubscriptionResource that shows subscriptions from ALL gateways.

### Deliverables

#### Week 3: Table & Data Layer
- [ ] UnifiedSubscription DTO with factory methods
- [ ] Status normalization across gateways
- [ ] Resource table with all columns
- [ ] Gateway badge column

#### Week 4: Filters, Tabs & View Page
- [ ] Gateway filter
- [ ] Status filter
- [ ] Plan filter
- [ ] Gateway tabs
- [ ] Issues tab
- [ ] View page with infolist
- [ ] Gateway-specific details section

#### Week 5: Actions & Create Form
- [ ] Cancel action (gateway-aware)
- [ ] Resume action
- [ ] Swap plan action with dynamic options
- [ ] Cancel immediately action
- [ ] Create form with wizard
- [ ] Gateway selection step
- [ ] Dynamic plan options
- [ ] Bulk actions

### Checklist
- [ ] `UnifiedSubscriptionResource`
- [ ] `UnifiedSubscription::fromStripe()`
- [ ] `UnifiedSubscription::fromChip()`
- [ ] Table columns
- [ ] Filters and tabs
- [ ] View page (infolist)
- [ ] Gateway details sections
- [ ] Cancel/Resume actions
- [ ] Swap plan action
- [ ] Create wizard
- [ ] Bulk cancel/export

---

## Phase 3: Multi-Gateway Dashboard (Weeks 6-8)

### Objective
Build unified revenue analytics dashboard with cross-gateway metrics.

### Deliverables

#### Week 6: Core Stats Widgets
- [ ] `TotalMrrWidget` with gateway breakdown
- [ ] `TotalSubscribersWidget` with counts
- [ ] Currency conversion utility
- [ ] Cross-gateway aggregation

#### Week 7: Comparison Widgets
- [ ] `GatewayComparisonWidget` line chart
- [ ] `GatewayDistributionWidget` doughnut
- [ ] Revenue normalization (USD base)

#### Week 8: Dashboard Page & Per-Gateway Widgets
- [ ] `UnifiedBillingDashboard` page
- [ ] `GatewayMetricsWidget` template
- [ ] Dynamic widget registration per gateway
- [ ] Export functionality
- [ ] Date range filters

### Checklist
- [ ] TotalMrrWidget
- [ ] TotalSubscribersWidget
- [ ] GatewayComparisonWidget
- [ ] GatewayDistributionWidget
- [ ] GatewayMetricsWidget
- [ ] UnifiedBillingDashboard page
- [ ] Currency conversion
- [ ] Export report action

---

## Phase 4: Customer Billing Portal (Weeks 9-11)

### Objective
Create unified customer-facing portal for managing subscriptions across gateways.

### Deliverables

#### Week 9: Portal Foundation & Dashboard
- [ ] Billing panel setup
- [ ] Customer authentication
- [ ] Portal mode in plugin
- [ ] Unified billing overview widget
- [ ] Subscription cards
- [ ] Payment method preview

#### Week 10: Subscriptions Management
- [ ] All subscriptions page
- [ ] Gateway tabs
- [ ] Subscription detail view
- [ ] Change plan action (gateway-aware)
- [ ] Cancel subscription flow
- [ ] Resume subscription

#### Week 11: Payment Methods & Invoices
- [ ] Payment methods page (grouped by gateway)
- [ ] Add payment method per gateway
- [ ] Set default per gateway
- [ ] All invoices page
- [ ] Invoice download
- [ ] New subscription wizard

### Checklist
- [ ] Billing panel provider
- [ ] UnifiedBillingOverviewWidget
- [ ] AllSubscriptionsPage
- [ ] Subscription detail actions
- [ ] Cancel with feedback
- [ ] PaymentMethodsPage
- [ ] Add method per gateway
- [ ] AllInvoicesPage
- [ ] NewSubscriptionPage wizard

---

## Phase 5: Invoicing & Reports (Weeks 12-13)

### Objective
Complete unified invoice management and cross-gateway reporting.

### Deliverables

#### Week 12: Unified Invoice Resource
- [ ] `UnifiedInvoiceResource`
- [ ] Invoice list with gateway column
- [ ] Invoice infolist
- [ ] Download PDF (gateway-specific)
- [ ] Gateway/status filters

#### Week 13: Reports
- [ ] Cross-gateway revenue report
- [ ] Subscription analytics report
- [ ] Gateway comparison report
- [ ] Export with gateway breakdown
- [ ] Scheduled reports (optional)

### Checklist
- [ ] UnifiedInvoiceResource
- [ ] Invoice table/infolist
- [ ] Download actions
- [ ] Revenue report page
- [ ] Analytics exports

---

## Phase 6: Gateway Management & Advanced (Weeks 14-15)

### Objective
Add gateway management UI and advanced features.

### Deliverables

#### Week 14: Gateway Management
- [ ] Gateway status page
- [ ] Gateway health indicators
- [ ] Default gateway configuration
- [ ] Gateway connectivity test
- [ ] Missing gateway setup guide

#### Week 15: Advanced Features
- [ ] Subscription migration (Gateway A → B)
- [ ] Migration preview
- [ ] Notification preferences
- [ ] Audit log integration
- [ ] Performance optimization

### Checklist
- [ ] GatewayManagementPage
- [ ] Gateway health checks
- [ ] Migration action
- [ ] Migration preview
- [ ] Caching layer
- [ ] Query optimization

---

## Phase 7: Polish & Documentation (Week 16)

### Objective
Finalize package with testing, documentation, and polish.

### Deliverables

#### Testing
- [ ] Gateway detection tests
- [ ] DTO conversion tests
- [ ] Resource tests
- [ ] Widget tests
- [ ] Portal flow tests

#### Documentation
- [ ] Installation guide
- [ ] Configuration reference
- [ ] Usage examples
- [ ] Portal setup guide
- [ ] API reference

#### Polish
- [ ] UI/UX refinements
- [ ] Error handling
- [ ] Loading states
- [ ] Accessibility

### Checklist
- [ ] Unit tests (≥85% coverage)
- [ ] Feature tests
- [ ] PHPStan level 6
- [ ] Documentation pages
- [ ] README update
- [ ] CHANGELOG
- [ ] Performance profiling

---

## Dependencies

### Required Before Start
- [ ] `aiarmada/cashier` package with:
  - [ ] GatewayManager working
  - [ ] Multi-gateway subscription queries
  - [ ] Payment method aggregation
  - [ ] Invoice aggregation

### Gateway Packages (One Required)
- [ ] `laravel/cashier` for Stripe
- [ ] `aiarmada/cashier-chip` for CHIP

### Optional Enhancements
- [ ] `aiarmada/filament-cashier-chip` for CHIP-specific UI

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | ≥85% |
| PHPStan Level | 6 |
| Documentation Pages | 12+ |
| Widgets | 8+ |
| Resources | 2 (Subscription, Invoice) |
| Portal Pages | 5+ |
| Gateways Supported | 2+ |
| Translations | 2 (en, ms) |

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Gateway API differences | Normalization layer (DTO), extensive testing |
| Currency conversion accuracy | Use reliable rates, cache, allow override |
| Missing gateway graceful handling | Feature flags, conditional registration |
| Performance with many subscriptions | Pagination, caching, lazy loading |
| Portal security | Scope all queries to auth user |

---

## Post-Launch Roadmap

### v1.1
- Additional gateway support (PayPal, others)
- Enhanced reporting
- Webhook event log viewer

### v1.2
- Subscription migration wizard
- Revenue forecasting
- Churn prediction

### v2.0
- Multi-tenancy support
- White-label portal
- API endpoints
- Mobile-responsive portal

---

## Navigation

**Previous:** [04-customer-portal.md](04-customer-portal.md)  
**Back to:** [PROGRESS.md](PROGRESS.md)
