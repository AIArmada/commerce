---
title: Csuite Context
package: csuite
status: current
surface: bundle
family: bundle
keywords:
  - bundle
  - metapackage
  - install
  - suite
  - starter
---

# Csuite Context

## Snapshot
- Composer: `aiarmada/commerce`
- Role: Metapackage bundle: one Composer dependency installing the curated Commerce suite. No runtime code.
- Triggers: bundle, metapackage, install, suite, starter
- Search first: `composer.json, docs, README.md`
- Related: `cart`, `inventory`, `vouchers`, `chip`, `docs`, `jnt`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts when the change crosses boundaries
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Installing the preselected suite in one step.
- Skip when: Runtime behavior — edit the underlying package, never csuite.
- Owner/security: No models; inherits each bundled package rules.

## Key surfaces

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
