# Filament Affiliates - Dedicated Pages

> **Document:** 04 of 06  
> **Package:** `aiarmada/filament-affiliates`  
> **Status:** ✅ Complete  
> **Last Updated:** December 2025

---

## Overview

Dedicated pages provide specialized interfaces for complex workflows that go beyond standard resource CRUD operations.

---

## FraudReviewPage

Dedicated fraud review queue for efficient signal processing.

### Purpose

Provide a focused interface for reviewing and actioning fraud signals without navigating away from the workflow.

### Layout

```
┌────────────────────────────────────────────────────────────────┐
│ FRAUD REVIEW QUEUE                              [Bulk Actions] │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Filters: [Severity ▼] [Rule ▼] [Status ▼] [Date Range 📅]     │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌────────────────────────────────────────────────────────────┐ │
│ │ 🔴 CRITICAL: SELF_REFERRAL                                 │ │
│ │                                                             │ │
│ │ Affiliate: AFF-001 (John Doe)                               │ │
│ │ Risk Points: 100                                            │ │
│ │ Detected: 2 minutes ago                                     │ │
│ │                                                             │ │
│ │ Evidence:                                                   │ │
│ │ • Owner ID matches conversion owner                         │ │
│ │ • Order: ORD-12345                                          │ │
│ │                                                             │ │
│ │ [Confirm Fraud] [Dismiss] [Suspend Affiliate]               │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌────────────────────────────────────────────────────────────┐ │
│ │ 🟠 HIGH: GEO_ANOMALY                                       │ │
│ │                                                             │ │
│ │ Affiliate: AFF-042 (Jane Smith)                             │ │
│ │ Risk Points: 40                                             │ │
│ │ Detected: 15 minutes ago                                    │ │
│ │                                                             │ │
│ │ Evidence:                                                   │ │
│ │ • Previous IP: 192.168.1.1 (Malaysia)                       │ │
│ │ • Current IP: 10.0.0.1 (Unknown)                            │ │
│ │ • Time diff: 3 minutes                                      │ │
│ │                                                             │ │
│ │ [Confirm Fraud] [Dismiss] [View History]                    │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### Actions

| Action | Description | Effect |
|--------|-------------|--------|
| Confirm Fraud | Confirm the fraud signal | Updates status, may trigger suspension |
| Dismiss | Mark as false positive | Updates status to dismissed |
| Suspend Affiliate | Immediate suspension | Updates affiliate status |
| View History | See affiliate's fraud history | Opens modal |

### Bulk Actions

- Bulk Dismiss Selected
- Bulk Confirm Selected
- Export Report

---

## PayoutBatchPage

Batch payout processing interface.

### Purpose

Enable efficient bulk payout processing with preview, validation, and execution.

### Layout

```
┌────────────────────────────────────────────────────────────────┐
│ PAYOUT BATCH PROCESSING                                         │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│ │ Pending: 45     │  │ Total: RM 45.2K │  │ Methods: 3      │ │
│ └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Filter by Method: [All ▼]  Min Amount: [RM 50    ]             │
│                                                                 │
│ ┌────────────────────────────────────────────────────────────┐ │
│ │ ☑ SELECT ALL                                                │ │
│ ├────────────────────────────────────────────────────────────┤ │
│ │ ☐ AFF-001 │ John Doe     │ RM 2,500.00 │ Bank     │ ✓ Tax  │ │
│ │ ☐ AFF-042 │ Jane Smith   │ RM 1,200.00 │ PayPal   │ ✓ Tax  │ │
│ │ ☑ AFF-089 │ Ali Ahmad    │ RM 890.50   │ Stripe   │ ✓ Tax  │ │
│ │ ☐ AFF-023 │ Siti Aminah  │ RM 456.00   │ Bank     │ ⚠ Tax  │ │
│ │ ☐ AFF-156 │ Ahmad Razak  │ RM 123.00   │ PayPal   │ ✓ Tax  │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ Selected: 1 │ Total: RM 890.50                                  │
│                                                                 │
│ [Export CSV] [Export PDF] [Process Selected] [Schedule Batch]  │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### Features

| Feature | Description |
|---------|-------------|
| Batch Selection | Select multiple payouts |
| Method Filtering | Filter by payout method |
| Tax Status | Show tax document status |
| Amount Threshold | Filter by minimum amount |
| Preview | Review before processing |

### Actions

| Action | Description |
|--------|-------------|
| Process Selected | Immediately process selected payouts |
| Schedule Batch | Schedule for later processing |
| Export CSV | Download CSV report |
| Export PDF | Download PDF report |
| Hold Selected | Put selected on hold |

---

## ReportsPage

Comprehensive reporting interface.

### Purpose

Provide analytics, reporting, and data visualization for affiliate program performance.

### Layout

```
┌────────────────────────────────────────────────────────────────┐
│ AFFILIATE REPORTS                                               │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Date Range: [Last 30 Days ▼]  Compare: [Previous Period ▼]     │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ SUMMARY                                                         │
│ ┌──────────┬──────────┬──────────┬──────────┬──────────┐      │
│ │ Revenue  │ Conv.    │ Clicks   │ CVR      │ EPC      │      │
│ │ RM 45.2K │ 2,345    │ 45,678   │ 5.1%     │ RM 0.99  │      │
│ │ ↑ 12%    │ ↑ 8%     │ ↓ 3%     │ ↑ 15%    │ ↑ 22%    │      │
│ └──────────┴──────────┴──────────┴──────────┴──────────┘      │
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐│
│ │                    REVENUE TREND                             ││
│ │      📈 [Chart: Revenue over time]                          ││
│ └─────────────────────────────────────────────────────────────┘│
│                                                                 │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ TOP PERFORMERS                    TOP SOURCES                   │
│ ┌─────────────────────┐          ┌─────────────────────┐       │
│ │ 1. AFF-001 RM 12.5K │          │ 1. Google    45%    │       │
│ │ 2. AFF-042 RM 8.9K  │          │ 2. Facebook  28%    │       │
│ │ 3. AFF-089 RM 6.2K  │          │ 3. Direct    15%    │       │
│ └─────────────────────┘          └─────────────────────┘       │
│                                                                 │
│ [Export Full Report] [Schedule Report] [Share Dashboard]       │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### Report Types

| Report | Description |
|--------|-------------|
| Performance Summary | Overview of key metrics |
| Revenue Breakdown | Revenue by affiliate, source, time |
| Conversion Funnel | Click → Attribution → Conversion flow |
| Cohort Analysis | Affiliate cohort performance |
| Fraud Analysis | Fraud signals and patterns |
| Payout Summary | Payout history and projections |

### Export Formats

| Format | Use Case |
|--------|----------|
| CSV | Data analysis |
| Excel | Detailed reports |
| PDF | Stakeholder presentations |

---

## Portal Pages

Affiliate-facing portal pages (separate from admin).

### PortalDashboard

Affiliate's personal dashboard.

### PortalLinks

Link management interface.

### PortalConversions

Conversion history view.

### PortalPayouts

Payout history and status.

### PortalRegistration

New affiliate registration form.

---

## Page Registration

Pages are registered in the service provider:

```php
class FilamentAffiliatesServiceProvider extends PackageServiceProvider
{
    public function packageBooted(): void
    {
        Filament::registerPages([
            FraudReviewPage::class,
            PayoutBatchPage::class,
            ReportsPage::class,
        ]);
    }
}
```

---

## Navigation

**Previous:** [03-widgets.md](03-widgets.md)  
**Next:** [05-integrations.md](05-integrations.md)
