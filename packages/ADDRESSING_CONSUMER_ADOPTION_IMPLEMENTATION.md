---
title: Implement Addressing Adoption Across Consumer Packages
package: aiarmada/addressing
status: implementation-instruction
surface: monorepo
family: core
---

# Implement Addressing Adoption Across Consumer Packages

## Purpose

Create a safe, staged adoption plan for using `aiarmada/addressing` across packages that currently store, format, transmit, snapshot, or infer address-like data.

This instruction is for agents working on consuming packages. It does not replace the core `aiarmada/addressing` package implementation. It explains how other packages should use it.

## Mandatory starting point

Before editing any package, every agent must:

1. Read `CONTEXT-MAP.md` if present.
2. Read the owning package `CONTEXT.md`.
3. Read `packages/addressing/CONTEXT.md`.
4. Read these addressing docs:
   - `packages/addressing/docs/06-consuming-packages.md`
   - `packages/addressing/docs/07-adoption-levels.md`
   - `packages/addressing/docs/08-package-playbooks.md`
   - `packages/addressing/docs/09-migration-recipes.md`
   - `packages/addressing/docs/10-contracts-and-examples.md`
5. Identify whether the target package needs DTO-only, snapshot, reusable storage, mapper, country resolver, or config normalization adoption.

## Non-negotiable rules

- Do not delete existing address columns or tables just because a package starts using `aiarmada/addressing`.
- Do not convert historical address records into mutable shared addresses.
- Do not make gateway/provider packages own address storage.
- Do not put order, event, shipment, tax, Chip, JNT, cashier, venue, institution, or customer-specific rules into `aiarmada/addressing`.
- Do not add database foreign-key constraints or cascades in consuming package migrations.
- Do not use `->constrained()` or `->cascadeOnDelete()`.
- Use UUIDs for new primary keys.
- Use configurable JSON column type if adding JSON columns in a package.
- Put orchestration in Actions, not controllers or fat models.
- Update the owning package docs when public behavior changes.
- Run package-scoped checks only.
- Pest/PHPUnit commands must include `--parallel`.

## High-level adoption principle

A package can use `aiarmada/addressing` at different levels:

```txt
Level 1: AddressData only
Level 2: AddressData + mappers/contracts
Level 3: AddressData JSON casts or snapshots
Level 4: Address model + HasAddresses for reusable saved addresses
Level 5: storage migration and cleanup of duplicated legacy columns
```

The level depends on address meaning, not package name.

## Decision tree

Ask these questions in order.

### 1. Is the address reusable and editable?

Examples:

- customer saved shipping address
- customer saved billing address
- institution address
- masjid address
- venue address
- branch address
- company/vendor saved address

Use:

```txt
Address model + addressables + HasAddresses
```

Eventually migrate old table/columns if they only duplicate reusable address storage.

### 2. Is the address historical or transaction-bound?

Examples:

- order shipping address
- order billing address
- event published location
- shipment origin/destination
- tax calculation context
- payment gateway transaction payload

Use:

```txt
AddressSnapshot or AddressData JSON
```

Do not link only to mutable customer/venue/institution address.

### 3. Is the address only a provider payload?

Examples:

- Chip client payload
- JNT request payload
- cashier gateway billing payload

Use:

```txt
AddressData mapper
```

Do not create address tables in provider packages.

### 4. Is it an IP/resolved location?

Examples:

- `signal_sessions.resolved_country_code`
- `signal_sessions.resolved_city`
- `signal_sessions.ip_address`

Use:

```txt
ResolvedLocationData + country/area resolver
```

Do not pretend IP geolocation is a full postal address.

### 5. Is it config display text?

Examples:

- `docs.company.address`
- `cashier-chip.vendor_address`

Use:

```txt
string remains valid; optional AddressData array support
```

Do not force database storage.

## Dependency rule

Use this rule before editing `composer.json`.

| Package usage | Dependency style |
|---|---|
| Stores reusable addresses using `HasAddresses` | hard require `aiarmada/addressing` |
| Stores address snapshots or casts JSON to `AddressData` | hard require if the package owns the feature |
| Only optionally formats config address arrays | `suggest` or optional `class_exists()` integration |
| Provider adapter maps payloads using `AddressData` | hard require if adapter is in monorepo standard; otherwise optional |
| Filament adapter for addressing | hard require core `aiarmada/addressing` |

Do not add a hard dependency when a package can remain standalone and only enhance behavior when addressing is installed.

## Minimum rollout order

1. Implement or verify public addressing APIs exist.
2. Update `commerce-support` contracts only if shared contracts are needed.
3. Add DTO/mappers to provider and gateway packages.
4. Add JSON casts/snapshot usage to shipping/orders/events.
5. Migrate reusable address owners such as customers, institutions, and venues.
6. Delete legacy columns only in a separate cleanup migration after all reads/writes have moved and tests pass.

## Success criteria

A consuming package adoption is successful only when:

- Address data is normalized through `AddressData` or a documented package-specific DTO.
- Historical addresses remain immutable snapshots.
- Reusable addresses use shared storage only when appropriate.
- Provider payloads are mapped at the boundary.
- Existing behavior has tests.
- Package docs explain the new address behavior.
- Package-scoped Pint/PHPStan/Pest checks pass.
