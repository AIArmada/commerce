---
title: Multitenancy
---

# Multitenancy

Filament Cart uses `commerce-support` owner scoping.

## Owner columns

Tenant-owned tables use:

- `owner_type`
- `owner_id`

The database may include an internal `owner_scope` column for nullable-owner uniqueness. Do not expose or authorize against `owner_scope`.

## Read paths

Resources and widgets query through owner-aware models. When owner mode is enabled, a missing owner fails fast unless the call site uses explicit global context.

## Write paths

Incoming IDs must belong to the current owner scope. Reusable helpers from `commerce-support` should be used for submitted-ID and foreign-key validation.

## Global rows

Global rows are ownerless rows. They may be visible when explicitly included, but mutating them requires explicit global context.

## Operational events

Emitted event payloads include `ownerType` and `ownerId` so Signals can record events under the correct owner context.
