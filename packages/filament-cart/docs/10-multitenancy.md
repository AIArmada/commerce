---
title: Multitenancy
---

# Multitenancy

Filament Cart uses `commerce-support` owner scoping.

## Core contract

Filament Cart treats snapshot rows and snapshot children as owner-scoped records.

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
