---
title: Persons Context
package: persons
status: planned
surface: domain
family: identity
---

# Persons Context

## Snapshot

- Composer: `aiarmada/persons`
- Role: reusable person identity layer. Canonical `persons` table plus normalized relational systems for multi-context names, titles, credentials, and affiliations. Assignment tables are polymorphic so any model can participate as a titleable/credentialable/affiliatable.
- Search first: `src/Models`, `src/Enums`, `src/Support`, `src/Contracts`, `src/Traits`, `config`, `database/migrations`, `docs`
- Related: `commerce-support` (primitives), `addressing` (optional: nationality + title/issuer country), `filament-persons` (future admin UI)

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when setup or publishing is involved

## Guardrails

- Owns identity-domain models only: `persons`, `person_names`, `title_categories`, `titles`, `title_issuers`, `title_assignments`, `credential_definitions`, `credential_assignments`, `affiliations`, `affiliation_roles`.
- Does NOT own event involvement, event roles, members, users, donations, reports, or share tracking. Those are event-domain or app-domain concerns. Event involvement links to a person through the events package's polymorphic `involveable`, wired at the application layer.
- Does NOT own institutions/organizations. `affiliations.institution_id` resolves to a host application model via `persons.models.institution` (config). The package stores it as a loose UUID.
- Assignment tables (`title_assignments`, `credential_assignments`, `affiliations`) are polymorphic and morph-alias-agnostic. The package never hardcodes the `'person'` alias; the application registers its morph map via `Persons::morphMap()`.
- Standalone installable. `addressing` and `spatie/laravel-medialibrary` are optional (`suggest`). Media collections on Person require `spatie/laravel-medialibrary`; the base Person model does not use it.
- UUID primary keys, `timestampTz` lifecycle columns, configurable JSON column type, no DB foreign-key constraints, no cascades, no soft deletes.
- Factories hardcode `'person'` as the morph alias default — this is the expected alias for test data; runtime code never hardcodes it.
- Update `docs/*.md` in the same pass when public behavior or config changes.
