---
title: Filament Persons Context
package: filament-persons
status: planned
surface: filament
family: identity
---

# Filament Persons Context

## Snapshot

- Composer: `aiarmada/filament-persons`
- Role: Filament admin UI for the persons identity system.
- Search first: `src/Resources`, `config`, `docs`
- Related: `persons` (domain package), `filament-events`

## Guardrails

- Owns Filament resources for Persons, Titles, TitleIssuers, CredentialDefinitions.
- Does NOT own domain models, migrations, data classes, or enums — those belong to `aiarmada/persons`.
- Follows the standard filament-* adapter conventions: navigation group via config, getNavigationGroup() reads config, no owner scoping in the package itself.
- Config-driven navigation sort.
