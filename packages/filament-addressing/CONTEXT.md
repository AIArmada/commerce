---
title: Filament Addressing Context
package: filament-addressing
status: current
surface: filament
family: foundation
keywords:
  - filament
  - admin
  - address-ui
  - import-export
---

# Filament Addressing Context

## Snapshot
- Composer: `aiarmada/filament-addressing`
- Role: Filament v5 admin for addressing: countries/states/cities/areas/postcodes, import/export, schemas.
- Triggers: filament, admin, address-ui, import-export
- Search first: `src/Resources, src/Schemas, src/Tables, config, docs`
- Related: `addressing`, `commerce-support`
- Paired: `addressing` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../addressing/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `addressing`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `addressing` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Admin UI for address reference data.
- Skip when: Domain rules — see addressing.
- Owner/security: No owner scope (core unscoped).

## Key surfaces
- Resources: `AddressAreaResource`, `AddressCityResource`, `AddressCountryResource`, `AddressResource`, `AddressSnapshotResource`, `AddressStateResource`, `PostalCodeResource`
- Actions/Services: `Support/GuardsAddressingUi`, `Support/ResolvesAddressingResources`, `Support/SingleAddressAreaSource`
- Config `filament-addressing.php`: `navigation`, `enabled`, `group`, `sort`, `icons`, `countries`, `states`, `cities`, `areas`, `addresses`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
