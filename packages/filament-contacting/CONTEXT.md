---
title: Filament Contacting Context
package: aiarmada/filament-contacting
status: active
surface: filament
family: contacting
---

# Filament Contacting Context

## Snapshot

Composer package: `aiarmada/filament-contacting`.

Role: Filament v5 adapter for `aiarmada/contacting`. Provides resources, relation managers, form schemas, table schemas, infolists, and optional import/export UI for contact methods, social profiles, and read-only contact snapshots.

Start search paths:

- `packages/filament-contacting/src`
- `packages/filament-contacting/config`
- `packages/filament-contacting/docs`
- `packages/filament-contacting/tests`

Related packages:

- `packages/contacting`
- `packages/commerce-support`, when owner scoping is enabled
- consuming Filament packages that embed contact/social relation managers

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../contacting/CONTEXT.md`

## Guardrails

This package is an adapter. It does not own contact/social models, migrations, normalization, verification, primary selection, snapshots, or communication sending.

Call core `aiarmada/contacting` Actions/Services for domain behavior.

Filament tenancy is not a security boundary. Resource queries, relation managers, and action handlers must remain owner-safe when core contacting models are owner-scoped.

Snapshots are read-only by default.

When public UI behavior or config changes, update docs in the same pass.