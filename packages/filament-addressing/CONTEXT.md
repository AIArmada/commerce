---
title: Filament Addressing Context
package: aiarmada/filament-addressing
status: current
surface: filament
family: addressing
---

## Snapshot

Composer package: `aiarmada/filament-addressing`.

This package is the Filament v5 adapter for `aiarmada/addressing`. It provides optional admin resources, relation managers, form schemas, table columns, and import/export surfaces for the core addressing domain.

Start searches in:

- `packages/filament-addressing/src`
- `packages/filament-addressing/config`
- `packages/filament-addressing/docs`
- `packages/addressing/CONTEXT.md`
- `packages/addressing/docs`

Related packages:

- `aiarmada/addressing`
- `commerce-support` for owner scoping primitives
- sibling `filament-*` packages for conventions

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../addressing/CONTEXT.md`
- `../addressing/docs/01-overview.md`
- `../addressing/docs/04-usage.md`

## Guardrails

This package is an adapter, not a domain owner.

It owns:

- Filament plugin registration.
- Addressing admin resources.
- Reusable Filament schemas and tables.
- Optional relation managers.
- Filament import/export wiring.

It does not own:

- Address models.
- Address migrations.
- Country data.
- Area import contracts.
- Address formatting, normalization, snapshots, or geocoding.
- Tenant ownership primitives.
- Event, venue, customer, shipping, cashier, or tax address workflows.

When a change crosses core addressing and Filament boundaries, read both contexts before editing.

Country and area reference data are global by default. Central address and snapshot resources are disabled by default because they may expose owner-scoped or historical data.
