---
title: Organizations Overview
---

## Purpose

`aiarmada/organizations` provides a reusable organization aggregate for
multi-tenant Laravel applications. It owns organization identity, lifecycle,
visibility, membership ownership invariants, and current-organization context.

## Boundary

The package does not own events, addresses, media, billing, moderation,
Livewire, or Filament. Applications provide profile extensions, public
projections, and domain-specific authorization policy.

## Core invariants

- Every created organization receives exactly one `Owner` membership.
- `created_by` is immutable provenance.
- Ownership transfers are transactional.
- The final owner cannot leave or be removed.
- Public queries must use the `public()` scope.

## What this package owns

- Model `Organization` (the org IS the tenant — no `HasOwner` on the model itself)
- Actions `CreateOrganization`, `ArchiveOrganization`, `RestoreOrganization`, `SuspendOrganization`, `MakeOrganizationPublic`, `MakeOrganizationPrivate`, `TransferOrganizationOwnership`
- `Resolvers/*` + `CurrentOrganizationMiddleware` — establishes the `OwnerContext` consumed by owner-aware packages
- Config `organizations.php`: `database`, `resolver`, `middleware` (`require_context`)

## Related packages

- `aiarmada/membership` — applications, invitations, member pivots, role sync for org subjects
- `aiarmada/filament-organizations` — Filament admin (`OrganizationResource`)
- `aiarmada/commerce-support` — the `OwnerContext`/`OwnerScope` primitives this package feeds
