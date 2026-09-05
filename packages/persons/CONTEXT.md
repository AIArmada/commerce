---
title: Persons Context
package: persons
status: planned
surface: domain
family: catalog-and-identity
keywords:
  - person
  - identity
  - title
  - credential
  - affiliation
  - names
---

# Persons Context

## Snapshot
- Composer: `aiarmada/persons`
- Role: Normalized person identity: canonical persons + multi-context names, titles, credentials, affiliations (polymorphic).
- Triggers: person, identity, title, credential, affiliation, names
- Search first: `src/Models, src/Enums, src/Support, config, database/migrations, docs`
- Related: `commerce-support`, `addressing`, `filament-persons`
- Paired: `filament-persons` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-persons/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns identity-domain models only: `persons`, `person_names`, `title_categories`, `titles`, `title_issuers`, `title_assignments`, `credential_definitions`, `credential_assignments`, `affiliations`, `affiliation_roles`.
- Does NOT own event involvement, event roles, members, users, donations, reports, or share tracking. Event involvement links via the events package polymorphic `involveable`, wired at the application layer.
- Does NOT own institutions/organizations. `affiliations.institution_id` is a loose UUID resolved via `persons.models.institution` config.
- Assignment tables are polymorphic and morph-alias-agnostic; the app registers its morph map (`persons_register_morph_map('person')`), runtime code never hardcodes it.
- UUID primary keys, `timestampTz` lifecycle columns, configurable JSON column type, no DB FK constraints/cascades, no soft deletes.
- If admin UI changes too, audit `filament-persons`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Person identity with titles/credentials/affiliations.
- Skip when: Event roles — see events; customer CRM — see customers.
- Owner/security: Shared identity by design; no owner scope.

## Key surfaces
- Models: `Affiliation`, `AffiliationRole`, `CredentialAssignment`, `CredentialDefinition`, `Person`, `PersonName`, `Title`, `TitleAssignment`, `TitleCategory`, `TitleIssuer`
- Actions/Services: `Actions/ReorderTitleAction`, `Support/ModelResolver`
- Config `persons.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `persons`, `person_names`, `title_categories`, `titles`, `title_issuers`, `title_assignments`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
