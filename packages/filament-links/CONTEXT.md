---
title: Filament Links Context
package: filament-links
status: current
surface: admin
family: growth-and-incentives
keywords:
  - filament
  - links-ui
  - redirect
  - click-tracking
---

# Filament Links Context

## Snapshot

- Composer: `aiarmada/filament-links`
- Role: Filament admin adapter for links: link resource, clicks history, lifecycle actions.
- Triggers: filament, links-ui, redirect, click-tracking
- Search first: `src/Resources, src/FilamentLinksPlugin.php, config, docs`
- Related: `links`, `filament-signals`
- Paired: `links` (core domain package)

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../links/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails

- Adapter only: resources, pages, tables, forms, and panel registration. No domain calculations or persistence rules.
- Navigation group and sort come from `filament-links` config via `getNavigationGroup()` / `getNavigationSort()`. Never use static `$navigationGroup`.
- Resource queries must stay owner-scoped (`OwnerUiScope`); revalidate IDs inside action handlers.
- Update `docs/*.md` in the same pass when admin behavior or config changes.

## Decide fast

- Use when: Admin UI for tracked links and click history.
- Skip when: Redirects, click capture, or lifecycle rules — see links.
- Owner/security: Owner-scoped reads via `OwnerUiScope`. Screens must stay behind panel auth.
