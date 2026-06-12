---
title: Filament Commerce Support
package: filament-commerce-support
status: current
surface: filament
family: foundation
---

# Filament Commerce Support

## Snapshot
- Composer: `aiarmada/filament-commerce-support`
- Role: Filament admin UI for commerce navigation management — runtime override engine and admin settings page.
- Search first: `src/Pages`, `src/Settings`, `src/Support`, `config`, `docs`
- Related: `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../commerce-support/CONTEXT.md` when navigation engine or contributor contract changes
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns the Filament settings page for navigation overrides.
- Navigation engine and contributor contract live in `commerce-support`.
- Does NOT replace per-package navigation defaults — only overrides them at runtime.
- Revalidates nothing server-side; this is a UI-only configuration layer.
