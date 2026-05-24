---
title: Overview
---

# Filament Tax Plugin

## Purpose

The `aiarmada/filament-tax` package is the Filament admin adapter for `aiarmada/tax`. It exposes tax configuration, exemption workflows, widgets, and settings through Filament resources and pages.

## What this package owns

- Filament resources for tax zones, classes, rates, and exemptions
- Tax settings page, dashboard widgets, and certificate download actions
- Filament-side authorization integration when `aiarmada/filament-authz` is installed

## What this package does not own

- Tax calculation, persistence, or result contracts; those stay in `aiarmada/tax`
- Checkout or order total orchestration
- Tenant resolution itself; it consumes owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/tax`](../../tax/docs/01-overview.md) — core tax engine and data model
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner-context and shared infrastructure
- [`aiarmada/filament-authz`](../../filament-authz/docs/01-overview.md) — optional policy and permission integration

## Main models services or surfaces

- **Resources** — `TaxZoneResource`, `TaxClassResource`, `TaxRateResource`, `TaxExemptionResource`
- **Pages** — `ManageTaxSettings`
- **Widgets** — `TaxStatsWidget`, `ExpiringExemptionsWidget`, `ZoneCoverageWidget`
- **Actions and support** — `DownloadTaxExemptionCertificateAction`, `FilamentTaxAuthz`

## Owner scoping and security notes

- The package should mirror the owner-scoping rules defined by `aiarmada/tax` and `commerce-support`
- Filtered option lists are not authorization; action handlers and resource queries still need owner-safe reads and writes underneath
- Settings-page access should be treated as a privileged surface, especially in multi-panel installations

## Features

- **Tax Zone Management** — Create and manage geographic tax zones with country, state, and postcode targeting
- **Tax Class Resources** — Define product categorization for different tax treatments
- **Tax Rate Configuration** — Set up tax rates with support for compound taxes and priority ordering
- **Tax Exemption Workflow** — Approve, reject, and track customer tax exemptions
- **Settings Page** — Configure global tax behavior without code changes
- **Dashboard Widgets** — At-a-glance tax statistics and expiring exemption alerts
- **Zone Coverage Overview** — Visual representation of all zones and their rates
- **Authorization Integration** — Seamless integration with `filament-authz` when available
- **Activity Logging** — Track all changes via Spatie Activity Log integration

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.0+
- `aiarmada/tax` package

## Quick Start

```bash
composer require aiarmada/filament-tax
```

Register the plugin in your panel:

```php
use AIArmada\FilamentTax\FilamentTaxPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTaxPlugin::make(),
        ]);
}
```

## Resources

The plugin provides four Filament resources:

| Resource | Description |
|----------|-------------|
| `TaxZoneResource` | Geographic zones with country/state/postcode targeting |
| `TaxClassResource` | Product categorization (standard, reduced, zero-rated) |
| `TaxRateResource` | Tax percentages linked to zones and classes |
| `TaxExemptionResource` | Customer exemptions with approval workflow |

## Widgets

| Widget | Description |
|--------|-------------|
| `TaxStatsWidget` | Counts of zones, rates, classes, and exemptions |
| `ExpiringExemptionsWidget` | Table of exemptions expiring within 30 days |
| `ZoneCoverageWidget` | Visual overview of all zones and their rate configurations |

## Architecture

```
filament-tax/
├── src/
│   ├── Actions/                    # Custom Filament actions
│   │   └── DownloadTaxExemptionCertificateAction.php
│   ├── Pages/
│   │   └── ManageTaxSettings.php   # Settings page
│   ├── Plugin/
│   │   └── FilamentTaxPlugin.php   # Main plugin class
│   ├── Resources/
│   │   ├── TaxZoneResource/
│   │   │   ├── Pages/             # List, Create, Edit, View
│   │   │   ├── RelationManagers/ # RatesRelationManager
│   │   │   ├── Schemas/          # Form schema
│   │   │   └── Tables/           # Table schema
│   │   ├── TaxClassResource/
│   │   ├── TaxRateResource/
│   │   └── TaxExemptionResource/
│   ├── Support/
│   │   └── FilamentTaxAuthz.php   # Authorization helper
│   ├── Widgets/
│   │   ├── TaxStatsWidget.php
│   │   ├── ExpiringExemptionsWidget.php
│   │   └── ZoneCoverageWidget.php
│   └── FilamentTaxServiceProvider.php
├── config/
│   └── filament-tax.php
└── resources/
    └── views/
        ├── pages/
        │   └── manage-tax-settings.blade.php
        └── widgets/
            └── zone-coverage.blade.php
```

## Feature Toggles

Control which features are available via the plugin:

```php
FilamentTaxPlugin::make()
    ->zones(true)       // Enable zone management
    ->classes(true)     // Enable class management
    ->rates(true)       // Enable rate management
    ->exemptions(true)  // Enable exemption management
    ->widgets(true)     // Enable dashboard widgets
    ->settingsPage(true); // Enable settings page
```

## Filament Version

This plugin is built for **Filament 5.0** which uses Livewire 4. The API is compatible with Filament v4, so v4 documentation examples work with minor adjustments.

## Related Packages

- [`aiarmada/tax`](../../tax/docs/01-overview.md) — Core tax calculation engine (required)
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — Shared utilities for multi-tenancy
- [`aiarmada/filament-authz`](../../filament-authz/docs/01-overview.md) — Authorization layer (optional)

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Widgets](05-widgets.md)
- [Settings](06-settings.md)
- [Customization](07-customization.md)
- [Core tax overview](../../tax/docs/01-overview.md)
