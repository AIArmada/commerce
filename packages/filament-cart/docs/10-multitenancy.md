---
title: Multitenancy
---

# Multitenancy

Filament Cart uses `commerce-support` owner scoping.

Filament owner configuration is not a write-through switch for the core Cart
package. filament-cart.owner.enabled and filament-cart.owner.include_global
fall back to their cart.owner.* counterparts only when unset. Enable
cart.owner.enabled explicitly when core carts or stored conditions must be
owner-scoped; installing the admin adapter alone does not change that setting.
For the stored-condition resource, enable both owner flags; the core
Condition model remains the authority for condition ownership.

## Core contract

Filament Cart treats snapshot rows and snapshot children as owner-scoped records.
Their owner configuration falls back to the core cart settings when the
Filament setting is unset, including auto-assignment on create.

- owner-scoped rows use `owner_type` + `owner_id`
- global rows use `null` + `null`
- malformed tuples are invalid and should not be coerced to global

## Owner columns

Tenant-owned tables use:

- `owner_type`
- `owner_id`

The database may include an internal `owner_scope` column for nullable-owner uniqueness. Do not expose or authorize against `owner_scope`.

## Read paths

Resources, widgets, dashboard queries, and relation lookups query through owner-aware models. When owner mode is enabled, a missing owner fails fast unless the call site uses explicit global context.

Snapshot read surfaces now apply `include_global` consistently through package helpers rather than ad hoc query differences.

## Write paths

Incoming IDs must belong to the current owner scope. Reusable helpers from `commerce-support` should be used for submitted-ID and foreign-key validation.

Use `OwnerWriteGuard` / package action guards for:

- condition application
- batch condition removal
- record actions
- any submitted foreign ID

## Global rows

Global rows are ownerless rows. They may be visible when explicitly included, but mutating them requires explicit global context.

Global-condition validation and application scope the current owner (or
explicit global context) before applying the active/global filter. In owner
mode, calls without either context fail closed instead of returning an
ambiguous set.

## Operational events

Operational event payloads use snake_case owner tuple fields:

- `owner_type`
- `owner_id`

This includes snapshot events and destroy/sync cleanup flows.

For PHP APIs (jobs/services/listeners), use Laravel-style camelCase fields/arguments:

- `ownerType`
- `ownerId`
- `ownerIsGlobal` (queue context)

## Jobs and listeners

Queued synchronization jobs should use `OwnerContextJob` (preferably via `OwnerScopedJob` + `OwnerJobContext`). Listeners and synchronizers should parse owner tuples through shared `commerce-support` tuple utilities rather than manually rebuilding owner context from arbitrary row data.

## Commands

`cart:mark-abandoned` is fail-closed in owner mode.

- without owner context: fails unless `--all-owners` is passed
- `--all-owners` requires explicit confirmation for mutation
- malformed tuples are skipped with warning by default
- `--strict-owner-tuples` aborts the command on malformed tuple data

## Filament-specific expectations

1. `getEloquentQuery()` must remain owner-safe.
2. Resource pages must not resolve cross-owner records.
3. Visible global rows remain read-only in tenant context.
4. Submitted IDs must be revalidated server-side.
