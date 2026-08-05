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
