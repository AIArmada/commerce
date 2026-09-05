---
title: Filament Persons Context
package: filament-persons
status: planned
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - persons-ui
  - titles
---

# Filament Persons Context

## Snapshot
- Composer: `aiarmada/filament-persons`
- Role: Filament v5 admin for persons/titles/issuers/credential definitions.
- Triggers: filament, persons-ui, titles
- Search first: `src/Resources, config, docs`
- Related: `persons`, `commerce-support`
- Paired: `persons` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../persons/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `persons`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `persons` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Person identity admin UI.
- Skip when: Identity rules — see persons.
- Owner/security: Explicitly unscoped (shared identity).

## Key surfaces
- Resources: `CredentialDefinitionResource`, `PersonResource`, `TitleIssuerResource`, `TitleResource`
- Config `filament-persons.php`: `navigation`, `group`, `resources`, `enabled`, `person`, `title`, `title_issuer`, `credential_definition`, `navigation_sort`, `person`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
