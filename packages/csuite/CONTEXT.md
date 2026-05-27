---
title: Csuite Context
package: csuite
status: current
surface: bundle
family: bundle
---

# Csuite Context

## Snapshot
- Composer: `aiarmada/commerce`
- Role: Metapackage bundle that installs a curated Commerce package set with one dependency.
- Search first: `composer.json`, `docs`, `README.md`
- Related: `cart`, `inventory`, `vouchers`, `chip`, `docs`, `jnt`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts for the concrete runtime package you are actually changing
6. `docs/02-installation.md` when install composition or dependency changes are involved

## Guardrails
- Owns bundle composition and installation experience only.
- Route runtime behavior changes to the underlying packages, not `csuite`.
- Update `docs/*.md` in the same pass when public behavior or config changes.
