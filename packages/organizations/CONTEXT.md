---
title: Organizations Context
package: organizations
status: current
surface: core
family: foundation
---

# Organizations Context

`aiarmada/organizations` owns the reusable tenant aggregate, organization
membership ownership invariants, lifecycle actions, and current-organization
context integration. It is deliberately independent of events, addresses,
media, billing, moderation, Livewire, and Filament.

Applications provide product-specific profile extensions, moderation policy,
public projections, and organization-owned domain records. Use
`CurrentOrganizationMiddleware` to establish the `OwnerContext` used by
owner-aware packages.
