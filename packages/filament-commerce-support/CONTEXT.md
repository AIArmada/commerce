---
title: Filament Commerce Support Context
package: filament-commerce-support
status: current
surface: filament
family: foundation
keywords:
  - filament
  - navigation
  - overrides
  - reference-data
---

# Filament Commerce Support Context

## Snapshot
- Composer: `aiarmada/filament-commerce-support`
- Role: Filament navigation manager + reference-data UI (currencies/languages/timezones read-only).
- Triggers: filament, navigation, overrides, reference-data
- Search first: `src/Pages, src/Resources, src/Settings, config, docs`
- Related: `commerce-support`
- Paired: `commerce-support` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../commerce-support/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `commerce-support`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `commerce-support` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Runtime nav overrides or reference-data browsing.
- Skip when: Owner primitives — see commerce-support.
- Owner/security: No owner scope.

## Key surfaces
- Resources: `CurrencyResource`, `LanguageResource`, `TimezoneResource`
- Actions/Services: `Support/NavigationConfigurator`
- Config `filament-commerce-support.php`: `navigation`, `enabled`, `group`, `settings_group`, `sort`, `permission`, `icons`, `currencies`, `languages`, `timezones`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
