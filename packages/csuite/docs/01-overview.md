---
title: Overview
---

# CSuite Package Overview

## Purpose

The `aiarmada/commerce` metapackage bundles a curated set of Commerce packages so an application can install a pre-selected suite with one Composer dependency.

## What this package owns

- Composer-level dependency composition for the package set listed in its manifest
- A bundle-oriented installation path and package map for teams that want a preselected starter suite

## What this package does not own

- Runtime domain logic, models, services, or admin UI of its own
- Package configuration beyond what the included packages expose individually
- Packages that are not listed in the metapackage manifest, even if they exist elsewhere in the monorepo

## Related packages

- [`aiarmada/cart`](../../cart/docs/01-overview.md), [`aiarmada/inventory`](../../inventory/docs/01-overview.md), [`aiarmada/vouchers`](../../vouchers/docs/01-overview.md), [`aiarmada/chip`](../../chip/docs/01-overview.md), [`aiarmada/cashier`](../../cashier/docs/01-overview.md), [`aiarmada/cashier-chip`](../../cashier-chip/docs/01-overview.md), [`aiarmada/docs`](../../docs/docs/01-overview.md), and [`aiarmada/jnt`](../../jnt/docs/01-overview.md) — core packages currently bundled by the metapackage
- The bundled Filament packages and `aiarmada/filament-authz` for admin surfaces included in the metapackage

## Main models services or surfaces

- **Bundle surface** — one-step Composer installation for the package set listed in `packages/csuite/composer.json`
- **Supporting docs** — installation, bundled package inventory, and configuration guidance for the suite

## Owner scoping and security notes

- Owner-scoping behavior is still owned by the underlying packages and `commerce-support`; installing the metapackage does not change those runtime rules
- Applications should configure owner resolution and security per package rather than assuming the bundle does it automatically

## Read next

- [Installation](02-installation.md)
- [Usage](04-usage.md)
- [Configuration](03-configuration.md)
- [Troubleshooting](99-troubleshooting.md)