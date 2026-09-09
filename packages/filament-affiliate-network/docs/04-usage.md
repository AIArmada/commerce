---
title: Usage
---

# Usage

This guide covers the shipped marketplace resources and the admin flows around them.

The plugin provides four Filament resources.

The site, offer, category, application, merchant dashboard, and network widget surfaces require the configured `affiliate-network.admin` ability. The marketplace page is intentionally public discovery and is not admin-gated; its write actions still validate the affiliate and owner context server-side.

Merchant dashboard counts and lists are scoped to the current merchant owner. The
network stats and top-offers widgets are separate, deliberate network-wide admin
reporting surfaces and cache their global result for 30 seconds.

The network owns discovery and link metrics. `affiliates` owns merchant-local
program membership, attribution, commissions, and payouts. When an imported
local offer has `external_program_id`, marketplace enrollment delegates to the
existing core program; it never creates a duplicate core program or network
application.

If checkout observes both boundaries for one order, keep duplicate guards
independent: reject a second network conversion when the order already carries
the `network_attribution` marker, and pass a stable `external_reference` to the
core conversion path so `affiliates` can apply its idempotency key. Network
metrics are discovery reporting; core commission and payout records are the
authoritative execution records.

## AffiliateSiteResource

Manage merchant sites/domains.

### Table Columns

- Name (searchable, sortable)
- Domain (searchable, copyable)
- Status (badge with colors)
- Offers count
- Verified at (toggleable)
- Created at (toggleable)

### Form Sections

**Site Details:**
- Name
- Domain (unique)
- Description

**Status:**
- Status (pending, verified, suspended, rejected)
- Verification method
- Verified at (read-only)

**Settings:**
- Settings (key-value)
- Metadata (key-value)

### Actions

| Action | Description |
|--------|-------------|
| Edit | Edit site details |
| Verify | Manually verify a pending site |
| Delete | Delete site |

### Status Colors

| Status | Color |
|--------|-------|
| `pending` | Warning (yellow) |
| `verified` | Success (green) |
| `suspended` | Danger (red) |
| `rejected` | Danger (red) |

---

## AffiliateOfferResource

Manage affiliate offers.

### Table Columns

- Name (searchable, sortable)
- Site name
- Category name (toggleable)
- Status (badge)
- Commission (formatted)
- Featured (icon)
- Public (icon)
- Applications count
- Created at (toggleable)

### Form Sections

**Offer Details:**
- Site (select from verified sites)
- Category (optional)
- Name (auto-generates slug)
- Slug
- Description
- Terms & Conditions

**Commission:**
- Commission type (percentage/fixed)
- Commission rate (basis points or cents)
- Currency
- Cookie duration (days)

**Settings:**
- Status
- Featured toggle
- Public toggle
- Requires approval toggle
- Landing page URL
- Start/end dates

**Advanced:**
- Restrictions (key-value)
- Metadata (key-value)

### Actions

| Action | Description |
|--------|-------------|
| Edit | Edit offer |
| Activate | Set status to active |
| Pause | Set status to paused |
| Delete | Delete offer |

### Filters

- Status
- Site
- Featured (ternary)
- Public (ternary)

---

## AffiliateOfferCategoryResource

Manage offer categories.

### Table Columns

- Name (searchable)
- Slug
- Parent category
- Offers count
- Active (icon)
- Sort order
- Created at (toggleable)

### Form Fields

- Parent category (optional)
- Name
- Slug
- Description
- Icon
- Sort order
- Active toggle

### Features

- Hierarchical categories (parent/child)
- Sort ordering
- Soft re-parenting on delete

---

## AffiliateOfferApplicationResource

Review affiliate applications.

### Table Columns

- Affiliate name
- Offer name
- Status (badge)
- Reason (toggleable)
- Submitted at
- Reviewed at (toggleable)

### Form Sections

**Application Details:**
- Offer (read-only)
- Affiliate (read-only)
- Status
- Application reason (read-only)

**Review:**
- Rejection reason
- Reviewed by
- Reviewed at

### Actions

| Action | Description |
|--------|-------------|
| View | View application details |
| Approve | Approve application |
| Reject | Reject with reason |
| Revoke | Revoke approved application |

### Filters

- Status (pending, approved, rejected, revoked)
- Offer
- Date range

### Status Colors

| Status | Color |
|--------|-------|
| `pending` | Warning (yellow) |
| `approved` | Success (green) |
| `rejected` | Danger (red) |
| `revoked` | Gray |

---

## Extending Resources

### Add Custom Resource

```php
namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource as BaseResource;

class AffiliateOfferResource extends BaseResource
{
    // Override navigation
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    
    // Add custom relation managers
    public static function getRelations(): array
    {
        return [
            RelationManagers\CreativesRelationManager::class,
            RelationManagers\LinksRelationManager::class,
        ];
    }
}
```

### Register Custom Resource

```php
FilamentAffiliateNetworkPlugin::make()
    ->resources([
        \App\Filament\Resources\AffiliateOfferResource::class,
        // Other default resources...
    ]);
```
