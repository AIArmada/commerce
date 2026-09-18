---
title: Overview
---

# Filament Links Package

## Purpose

The `aiarmada/filament-links` package is the Filament admin adapter for [`aiarmada/links`](../../links/docs/01-overview.md). It owns link management resources, click history, and lifecycle actions. Domain logic stays in the core package.

## What this package owns

- `LinkResource`: link CRUD, click counters, lifecycle actions
- Clicks relation manager: per-click device, referrer, and UTM history
- The `FilamentLinksPlugin` panel registration

## What this package does not own

- Link records, redirects, click capture, or events; see [`aiarmada/links`](../../links/docs/01-overview.md)

## Related packages

- [`aiarmada/links`](../../links/docs/01-overview.md) — core domain package

## Owner scoping and security notes

- Resource queries are owner-scoped via `OwnerUiScope`; UI filtering is not authorization
- Link management screens must stay behind panel authentication

## Requirements

- PHP 8.4+
- `aiarmada/links`
- Filament v5

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
