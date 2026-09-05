---
title: Filament Organizations Overview
---

## Purpose

`aiarmada/filament-organizations` is the Filament v5 adapter for
`aiarmada/organizations`. It provides the generic organization resource,
member and invitation managers, and lifecycle/ownership actions.

The package does not own application profile fields, branding, moderation,
translations, or product-specific permissions.

## What this package owns

- `OrganizationResource` (generic org CRUD; app-specific profile fields stay in the host app)
- Member and invitation relation managers (backed by `aiarmada/membership` actions)
- Lifecycle/ownership actions (archive, restore, suspend, visibility, ownership transfer — all delegated to `aiarmada/organizations` actions)
- Config `filament-organizations.php`: `navigation`, `resources`

## Related packages

- `aiarmada/organizations` — tenant aggregate, ownership invariants, current-org context (source of truth)
- `aiarmada/membership` — applications, invitations, member pivots behind the managers
