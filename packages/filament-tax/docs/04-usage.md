---
title: Usage
---

# Usage

This guide covers the resource-level tax administration workflows exposed through the plugin.

The plugin provides four Filament resources for managing tax configuration.

## Tax Zone Resource

Manages geographic tax zones that determine which tax rates apply based on customer location.

### Navigation

- **Icon:** `heroicon-o-globe-alt`
- **Group:** Tax (configurable)
- **Sort:** 1

### List View

| Column | Description |
|--------|-------------|
| Zone | Zone display name, with `code` as description |
| Type | Badge: country / state / postcode |
| Countries | Comma-joined ISO country codes |
| Rates | Count of tax rates in the zone |
| Priority | Numeric, sortable |
| Default | Boolean icon (fallback zone) |
| Active | Boolean icon |

**Filters:**
- Type (country / state / postcode)
- Active (ternary)

**Actions:**
- View zone details
- Edit zone

**Bulk Actions:**
- Delete selected zones

Zone bulk mutations are revalidated server-side with `OwnerWriteGuard` using owner-only scope (`includeGlobal: false`).

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Basic Information                            │
├─────────────────────────────────────────────┤
│ Name*         [__________________________]  │
│ Code*         [__________________________]  │
│ Description   [__________________________]  │
│               [__________________________]  │
├─────────────────────────────────────────────┤
│ Geographic Targeting                         │
├─────────────────────────────────────────────┤
│ Countries     [Select countries...      ▼]  │
│ States        [__________________________]  │
│               (comma-separated)              │
│ Postcodes     [__________________________]  │
│               (comma-separated, supports *)  │
├─────────────────────────────────────────────┤
│ Settings                                     │
├─────────────────────────────────────────────┤
│ Priority      [10___] (higher = checked     │
│                        first)                │
│ ☑ Active                                    │
│ ☐ Default zone                              │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | TextInput | Yes | Display name for the zone |
| code | TextInput | Yes | Unique identifier (uppercase) |
| type | Select | Yes | country / state / postcode |
| description | Textarea | No | Optional description |
| countries | TagsInput | No | ISO country codes |
| states | TagsInput | No | State/province codes |
| postcodes | TagsInput | No | Postcode patterns (`43*`, `40000-49999`) |
| priority | TextInput | No | Resolution priority (default: 0) |
| is_active | Toggle | No | Enable/disable zone |
| is_default | Toggle | No | Use as fallback zone |

### Relation Managers

#### Rates Relation Manager

When viewing a zone, the Rates tab shows all tax rates for that zone:

| Column | Description |
|--------|-------------|
| Name | Rate display name |
| Tax Class | Product category |
| Rate | Percentage (formatted) |
| Compound | Whether rate compounds |
| Shipping | Applies to shipping |
| Active | Rate status |

**Inline Actions:**
- Create new rate for this zone
- Edit rate
- Delete rate

---

## Tax Class Resource

Manages product categorization for different tax treatments.

### Navigation

- **Icon:** `heroicon-o-tag`
- **Group:** Tax
- **Sort:** 2

### List View

| Column | Description |
|--------|-------------|
| Name | Class display name |
| Slug | Unique identifier |
| Description | Optional description |
| Position | Display order |
| Default | Whether this is the default class |
| Active | Class status |

**Filters:**
- Active only

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Tax Class Details                            │
├─────────────────────────────────────────────┤
│ Name*         [__________________________]  │
│ Slug*         [__________________________]  │
│               (auto-generated from name)     │
│ Description   [__________________________]  │
│               [__________________________]  │
├─────────────────────────────────────────────┤
│ Settings                                     │
├─────────────────────────────────────────────┤
│ Position      [0____]                        │
│ ☑ Active                                    │
│ ☐ Default class                             │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | TextInput | Yes | Display name |
| slug | TextInput | Yes | Unique identifier for code reference |
| description | Textarea | No | Explain when to use this class |
| position | TextInput | No | Sort order (lower = first) |
| is_active | Toggle | No | Enable/disable class |
| is_default | Toggle | No | Use as fallback class |

### Common Tax Classes

| Slug | Example Use |
|------|-------------|
| `standard` | Normal taxable goods |
| `reduced` | Essential items with lower rate |
| `zero-rated` | Tax-free but reportable |
| `exempt` | Not subject to tax |
| `digital` | Digital goods/services |

**Bulk Actions:**
- Delete selected classes

Class bulk mutations are revalidated server-side with `OwnerWriteGuard` using owner-only scope (`includeGlobal: false`).

---

## Tax Rate Resource

Manages tax percentages applied to products and shipping.

### Navigation

- **Icon:** `heroicon-o-calculator`
- **Group:** Tax
- **Sort:** 2

### List View

| Column | Description |
|--------|-------------|
| Zone | Associated tax zone |
| Name | Rate display name |
| Tax Class | Product category |
| Rate | Percentage display |
| Priority | Compound calculation order |
| Created | Creation timestamp (toggleable) |

**Filters:**
- Zone (dropdown)
- Tax class (dropdown: standard / reduced / zero / exempt)
- Active (ternary)
- Compound (ternary)

**Bulk Actions:**
- Activate selected rates
- Deactivate selected rates
- Delete selected rates

Rate bulk mutations are revalidated server-side with `OwnerWriteGuard` using owner-only scope (`includeGlobal: false`).

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Tax Rate Details                             │
├─────────────────────────────────────────────┤
│ Name*         [__________________________]  │
│ Zone*         [Select zone...           ▼]  │
│ Tax Class*    [Select class...          ▼]  │
├─────────────────────────────────────────────┤
│ Rate Configuration                           │
├─────────────────────────────────────────────┤
│ Rate (%)*     [6.00___]                     │
│               (stored as basis points)       │
│ Priority      [10___]                        │
├─────────────────────────────────────────────┤
│ Options                                      │
├─────────────────────────────────────────────┤
│ ☐ Compound                                  │
│   (Calculate on amount + previous taxes)     │
│ ☑ Applies to shipping                       │
│ ☑ Active                                    │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | TextInput | Yes | Display name (e.g., "SST 6%") |
| zone_id | Select | Yes | Tax zone this rate belongs to |
| tax_class | Select | Yes | Product class for rate matching |
| rate | TextInput | Yes | Rate percentage (converted to basis points) |
| priority | TextInput | No | Order for compound calculation |
| is_compound | Toggle | No | Calculate on subtotal + previous taxes |
| is_shipping | Toggle | No | Apply to shipping charges |
| is_active | Toggle | No | Enable/disable rate |

### Rate Entry

The rate field accepts a percentage value and converts it to basis points for storage:

- Enter: `6` or `6.00`
- Stored: `600` (basis points)
- Displayed: `6.00%`

---

## Tax Exemption Resource

Manages customer tax exemptions with approval workflow.

### Navigation

- **Icon:** `heroicon-o-shield-exclamation`
- **Group:** Tax
- **Sort:** 4
- **Badge:** Count of pending exemptions

### List View

| Column | Description |
|--------|-------------|
| Customer | Exemptable entity name/ID |
| Certificate | Exemption certificate number |
| Zone | Tax zone (or "All Zones") |
| Status | Lifecycle state badge |
| Starts At | When exemption becomes active |
| Expires At | When exemption ends |

**Filters:**
- By status
- By zone
- Expiring in 30 days
- Expired

**Record Actions:**
- View
- Edit
- Download certificate
- Approve
- Renew
- Delete

**Bulk Actions:**
- Approve selected
- Reject selected
- Delete selected

Exemption bulk mutations are revalidated server-side with `OwnerWriteGuard` using owner-only scope (`includeGlobal: false`).

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Exemption Request                            │
├─────────────────────────────────────────────┤
│ Customer Type [Select model...          ▼]  │
│ Customer ID   [Select entity...         ▼]  │
├─────────────────────────────────────────────┤
│ Scope                                        │
├─────────────────────────────────────────────┤
│ Tax Zone      [All Zones                ▼]  │
│               (leave empty for all zones)    │
├─────────────────────────────────────────────┤
│ Validity Period                              │
├─────────────────────────────────────────────┤
│ Starts At     [📅 ___________________]      │
│ Expires At    [📅 ___________________]      │
├─────────────────────────────────────────────┤
│ Details                                      │
├─────────────────────────────────────────────┤
│ Reason*       [__________________________]  │
│               [__________________________]  │
│ Certificate # [__________________________]  │
│ Document      [📎 Upload file]              │
├─────────────────────────────────────────────┤
│ Status                                       │
├─────────────────────────────────────────────┤
│ Status        [Pending Review           ▼]  │
│ Rejection     [__________________________]  │
│ Reason        (internal notes)               │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| exemptable_type | Select | Yes | Model class (e.g., Customer) |
| exemptable_id | Select | Yes | Entity ID (owner-scoped options) |
| tax_zone_id | Select | No | Limit to specific zone (null = all) |
| starts_at | DatePicker | No | Start of validity period |
| expires_at | DatePicker | No | End of validity period |
| reason | Textarea | Yes | Justification for exemption |
| certificate_number | TextInput | No | External certificate reference |
| document_path | FileUpload | No | Supporting documentation |
| status | Select | Yes | pending, under_review, approved, rejected, revoked, expired |
| rejection_reason | Textarea | No | Internal admin notes |

### Approval Workflow

`status` is a spatie/laravel-model-states value cast. The shipped states are:

1. **pending** — Newly created or re-submitted
2. **under_review** — Picked up for manual review
3. **approved** — Admin approved, exemption active if dates valid
4. **rejected** — Admin rejected, exemption not applied
5. **revoked** — Previously approved, later withdrawn
6. **expired** — `expires_at` has passed

The table offers **Approve** and **Renew** as record actions and **Approve** /
**Reject** / **Delete** as bulk actions. There is no per-row *Reject* action —
use the bulk action, or edit the record's `status` directly.

### Certificate Download

`DownloadTaxExemptionCertificateAction` provides secure certificate download.
It is a plain class with an `execute()` method (it does **not** use
`lorisleiva/laravel-actions`, so there is no `::run()` entry point):

```php
// Path is validated to prevent directory traversal
// Downloads file from the configured disk
app(DownloadTaxExemptionCertificateAction::class)->execute($exemption);
```

Security features:
- Path validation against traversal attacks
- Existence check before download
- Proper content-disposition headers
