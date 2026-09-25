---
title: Usage
---

# Usage

This guide covers the shipped marketplace resources and the admin flows around them.

The plugin provides four Filament resources (sites, offers, categories, applications), a merchant dashboard page, and two network reporting widgets.

The site, offer, category, application, merchant dashboard, and network widget surfaces require the configured `affiliate-network.admin` ability.

Merchant dashboard counts and lists are scoped to the current merchant owner. The
network stats and top-offers widgets are separate, deliberate network-wide admin
reporting surfaces and cache their global result for 30 seconds.

The network owns discovery, enrollment, and link metrics: every offer
enrolls through network applications, and joining never requires or creates
a merchant-side account. `affiliates` owns merchant-local attribution,
commissions, and payouts; program memberships stay merchant-side only and
are never read or written by marketplace enrollment.

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
- Domain (unique; normalized to lowercase and validated as a bare domain without scheme, path, or whitespace)
- Description

**Status:**
- Status (pending, verified, suspended, rejected)
- Verification method
- Verified at (read-only; stamped when the status becomes verified and cleared when it leaves verified)

**Settings:**
- Settings (key-value)
- Metadata (key-value)

### Actions

| Action | Description |
|--------|-------------|
| Edit | Edit site details |
| Verify | Manually verify a pending site (runs inside the site's owner context) |
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
- Site (async search over verified sites)
- Category (optional; async search over active categories)
- Name (auto-generates slug)
- Slug (unique per site)
- Description
- Terms & Conditions

**Commission:**
- Commission type (percentage/fixed)
- Commission rate (whole-number basis points or minor units; negatives rejected)
- Currency (three-letter code)
- Cookie duration (whole days; negatives rejected)

**Settings:**
- Status
- Featured toggle
- Public toggle
- Requires approval toggle
- Landing page URL
- Start/end dates (end must be on or after start)

**Advanced:**
- Restrictions (key-value)
- Metadata (key-value)

### Actions

| Action | Description |
|--------|-------------|
| Edit | Edit offer |
| Activate | Publish via the domain update action (stamps published_at, clears archived_at) |
| Pause | Archive via the domain update action (stamps archived_at) |
| Delete | Delete offer |

Activate and pause run inside the owning site's owner context and fire the domain `OfferUpdated` event.

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

- Parent category (optional; async search; the record itself is excluded)
- Name
- Slug
- Description
- Icon
- Sort order
- Active toggle

### Features

- Hierarchical categories (parent/child)
- Cycle protection: a category cannot be assigned to itself or one of its descendants
- Sort ordering
- Soft re-parenting on delete

---

## AffiliateOfferApplicationResource

Review affiliate applications.

### Table Columns

- Affiliate code (searchable, sortable)
- Affiliate email (display-only; it is a virtual accessor, not a column)
- Offer name (searchable, sortable)
- Status (badge)
- Reason (toggleable)
- Submitted at
- Reviewed at (toggleable)

Approve, reject, revoke, and bulk-approve run each mutation inside the owning affiliate's owner context so cross-tenant review works when owner scoping is enabled.

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
