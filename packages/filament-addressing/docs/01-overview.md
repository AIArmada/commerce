---
title: Filament Addressing Overview
---

## Purpose

`aiarmada/filament-addressing` is the Filament v5 adapter for `aiarmada/addressing`.

It gives applications admin UI for:

- Address countries.
- Address areas.
- Optional central address inspection.
- Optional read-only address snapshot inspection.
- CSV import/export surfaces for area reference data.
- Reusable address form schemas for other Filament resources.
- An opt-in address relation manager.

:::warning
This package is not the address domain owner. The core `aiarmada/addressing` package owns models, migrations, import contracts, country data, formatting, normalization, and snapshots.
:::

## Default Resources

Enabled by default:

- Address countries.
- Address areas.

Disabled by default:

- Addresses.
- Address snapshots.

Countries are read-only by default. Snapshots are read-only by design.

## Why Addresses Are Disabled By Default

Actual address records may belong to customers, institutions, venues, orders, shipments, or other owner-scoped models.

A central address resource can accidentally expose cross-tenant or historical data if enabled carelessly. For that reason, the package ships with address and snapshot resources disabled.

Enable them only when your panel is truly admin-safe and owner scoping has been verified.

## Package Boundary

This package provides UI only.

Use core `aiarmada/addressing` for:

- `AddressData`
- `AddressCountry`
- `AddressArea`
- `Address`
- `AddressSnapshot`
- `HasAddresses`
- area import contracts
- country data
- formatting and normalization
