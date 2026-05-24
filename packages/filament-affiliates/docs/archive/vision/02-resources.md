# Filament Affiliates - Resources

> **Document:** 02 of 06  
> **Package:** `aiarmada/filament-affiliates`  
> **Status:** ✅ Complete  
> **Last Updated:** December 2025

---

## Overview

This document outlines all Filament Resources in the `filament-affiliates` package, providing full CRUD operations for affiliate program management.

---

## AffiliateResource

Main resource for managing affiliates.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `code` | Text | Unique affiliate code |
| `name` | Text | Affiliate name |
| `status` | Badge | Active/Pending/Suspended |
| `commission_type` | Badge | Percentage/Fixed |
| `commission_rate` | Money | Commission rate/amount |
| `conversions_count` | Count | Total conversions |
| `created_at` | DateTime | Registration date |

### Form Fields

```php
Forms\Components\Section::make('Affiliate Information')
    ->schema([
        Forms\Components\TextInput::make('code')
            ->required()
            ->unique(ignoreRecord: true),
        Forms\Components\TextInput::make('name')
            ->required(),
        Forms\Components\Select::make('status')
            ->options(AffiliateStatus::class)
            ->required(),
    ]),

Forms\Components\Section::make('Commission Settings')
    ->schema([
        Forms\Components\Select::make('commission_type')
            ->options(CommissionType::class)
            ->required(),
        Forms\Components\TextInput::make('commission_rate')
            ->numeric()
            ->required(),
        Forms\Components\Select::make('currency')
            ->options(['MYR', 'USD', 'SGD'])
            ->required(),
    ]),
```

### Actions

| Action | Description |
|--------|-------------|
| View | View affiliate details |
| Edit | Edit affiliate settings |
| Delete | Soft delete affiliate |
| Approve | Approve pending affiliate |
| Suspend | Suspend active affiliate |

### Bulk Actions

| Action | Description |
|--------|-------------|
| Approve Selected | Bulk approve pending affiliates |
| Suspend Selected | Bulk suspend affiliates |
| Export Selected | Export to CSV/Excel |
| Delete Selected | Bulk soft delete |

### Relation Managers

- `ConversionsRelationManager` - List affiliate's conversions
- `PayoutsRelationManager` - List affiliate's payouts

---

## AffiliateConversionResource

Resource for viewing and managing conversions.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `affiliate.name` | Text | Related affiliate |
| `order_reference` | Text | Order ID |
| `subtotal_minor` | Money | Order subtotal |
| `commission_minor` | Money | Commission amount |
| `status` | Badge | Pending/Approved/Paid |
| `occurred_at` | DateTime | Conversion date |

### Filters

| Filter | Description |
|--------|-------------|
| Status | Filter by conversion status |
| Affiliate | Filter by affiliate |
| Date Range | Filter by date range |

### Actions

| Action | Description |
|--------|-------------|
| View | View conversion details |
| Approve | Approve pending conversion |
| Reject | Reject conversion |

---

## AffiliatePayoutResource

Resource for payout management.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `affiliate.name` | Text | Related affiliate |
| `amount_minor` | Money | Payout amount |
| `currency` | Text | Currency code |
| `status` | Badge | Pending/Processing/Completed/Failed |
| `scheduled_at` | DateTime | Scheduled date |
| `completed_at` | DateTime | Completion date |

### Infolist Sections

```php
Infolists\Components\Section::make('Payout Details')
    ->schema([
        Infolists\Components\TextEntry::make('amount')
            ->money(),
        Infolists\Components\TextEntry::make('status')
            ->badge(),
        Infolists\Components\TextEntry::make('method'),
    ]),

Infolists\Components\Section::make('Timeline')
    ->schema([
        Infolists\Components\RepeatableEntry::make('events')
            ->schema([
                Infolists\Components\TextEntry::make('status'),
                Infolists\Components\TextEntry::make('notes'),
                Infolists\Components\TextEntry::make('created_at'),
            ]),
    ]),
```

### Relation Managers

- `ConversionsRelationManager` - Conversions included in payout

---

## AffiliateProgramResource

Resource for program configuration.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `name` | Text | Program name |
| `status` | Badge | Active/Draft/Archived |
| `default_commission_rate` | Percent | Default rate |
| `members_count` | Count | Active members |

### Form Sections

- Program Information (name, description, status)
- Commission Settings (type, rate, currency)
- Tier Configuration (tier levels)
- Creative Assets (banners, links)

---

## AffiliateFraudSignalResource

Resource for fraud signal review.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `affiliate.name` | Text | Related affiliate |
| `rule_code` | Badge | CLICK_VELOCITY, GEO_ANOMALY, etc. |
| `severity` | Badge | Low/Medium/High/Critical |
| `risk_points` | Number | Risk score points |
| `status` | Badge | Detected/Reviewed/Dismissed/Confirmed |
| `detected_at` | DateTime | Detection timestamp |

### Filters

| Filter | Description |
|--------|-------------|
| Severity | Filter by severity level |
| Status | Filter by review status |
| Rule Code | Filter by fraud rule |

### Actions

| Action | Description |
|--------|-------------|
| Review | Mark as reviewed |
| Dismiss | Dismiss false positive |
| Confirm | Confirm fraud signal |

---

## Resource Registration

All resources are registered via the plugin:

```php
class FilamentAffiliatesPlugin implements Plugin
{
    public function register(Panel $panel): void
    {
        $panel->resources([
            AffiliateResource::class,
            AffiliateConversionResource::class,
            AffiliatePayoutResource::class,
            AffiliateProgramResource::class,
            AffiliateFraudSignalResource::class,
        ]);
    }
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-widgets.md](03-widgets.md)
