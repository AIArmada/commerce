---
title: Usage
---

# Usage

This guide covers the shipped admin resources and the flows around them.

The plugin provides four Filament resources (sites, offers, categories, applications), a merchant dashboard page, and two network reporting widgets. There is no marketplace page in this package.

The site, offer, category, application, merchant dashboard, and network widget surfaces require the configured `affiliate-network.admin` ability.

Merchant dashboard counts and lists are scoped to the current merchant owner. The
network stats and top-offers widgets are separate, deliberate network-wide admin
reporting surfaces and cache their global result for 30 seconds.

The network owns discovery, enrollment, link metrics, and its own money
legs: every offer enrolls through network applications, and joining never
requires or creates a merchant-side account. `affiliates` owns
merchant-local attribution, commissions, and payouts; program memberships
stay merchant-side only and are never read or written by marketplace
enrollment. Merchant-ledger postings arrive through the fulfillment step,
never inline.

If checkout observes both boundaries for one order, the last-touch decider
settles it: a provisional network leg is confirmed on a network win and
superseded on an engine win, so exactly one side pays. Network legs are
the network money source of truth; engine commission and payout records
are the merchant execution records.

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

**Catalog Sync:**
- Catalog URL (merchant API base; empty for locally-managed offers)
- Catalog API token (stored encrypted; setting it stamps issuance; leave empty to keep)

**Settings:**
- Settings (key-value)
- Metadata (key-value)

### Actions

| Action | Description |
|--------|-------------|
| Edit | Edit site details |
| Verify | Manually verify a pending site (runs inside the site's owner context) |
| Delete | Delete site |

The edit page adds two header actions: **Sync catalog** (pull the
merchant catalog now) and **Rotate catalog token** (issues a fresh
postback token; the previous one dies immediately and the new plaintext
is shown once in a persistent notification).

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
- Fee in basis points (toggleable; blank uses the configured default)
- Source (badge: synced/manual)
- Featured (icon)
- Visibility (badge)
- Applications count
- Created at (toggleable, hidden by default)

### Form Sections

**Offer Details:**
- Site (async search over verified sites)
- Category (optional; async search over active categories)
- Name (auto-generates slug)
- Slug (unique per site)
- Description
- Terms & Conditions

**Commission:**
- Base rate in basis points (percentage; negatives rejected)
- Fixed amount in minor units (per conversion; takes precedence when set)
- Currency (three-letter code)
- Cookie duration (whole days; negatives rejected)
- Source (synced/manual; editing any rate field flips to manual)
- Network fee in basis points (marketplace take-rate; empty uses the configured default)
- Volume tiers (repeater: floor in minor units + rate in basis points)

**Settings:**
- Status (draft/published/archived)
- Visibility (public/private/unlisted)
- Featured toggle
- Requires approval toggle
- Landing page URL
- Start/end dates (end must be on or after start)

**Advanced:**
- Restrictions (key-value)
- Metadata (key-value)

### Relation Managers

The edit page carries two read-only relation tabs:

- **Links:** issued tracking links with clicks, conversions, and revenue,
  plus a reconcile action proving every counted conversion posted to the
  merchant ledger exactly once.
- **Legs:** posted money legs (reference, revenue, commission, fee,
  payout, status). Operators cannot edit legs, but a **Reverse**
  action on posted legs records a reason and posts the negated
  companion leg.

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
- Visibility
- Site
- Featured (ternary)

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

### Add a Custom Resource

Every shipped resource is `final`. Write your own resource and reuse the
shipped schema/table classes:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AffiliateOfferResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 1;
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOffersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinksRelationManager::class,
            RelationManagers\LegsRelationManager::class,
        ];
    }
}
```

### Register the Custom Resource

`FilamentAffiliateNetworkPlugin` registers a fixed set and exposes no
`resources()` method. Register your own resource from the panel provider
instead:

```php
$panel->resources([
    \App\Filament\Resources\AffiliateOfferResource::class,
    // ...plus any default resources you still want
]);
```
