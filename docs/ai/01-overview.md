---
title: AI Retrieval Layer
status: current
---

# AI Retrieval Layer

This guide explains the AI-oriented documentation layer for the Commerce monorepo.

It exists to make package ownership, package selection, and cross-package navigation easier for both humans and coding assistants.

## What this layer includes

### `CONTEXT.md`

The short root-level AI dispatch file.

Use it for:

- quick package routing,
- read-order setup,
- monorepo-wide non-negotiables,
- deciding which package context to open next.

### `CONTEXT-MAP.md`

The detailed root-level architecture map.

Use it for:

- package routing heuristics,
- monorepo-wide invariants,
- owner-scoping rules,
- core-vs-Filament boundaries,
- family-level package grouping.

### `packages/<pkg>/CONTEXT.md`

The package-level AI entrypoint.

Use it for:

- package ownership boundaries,
- paired-package routing,
- the local docs read order,
- high-signal folders to inspect first.

### `docs/ai/package-manifests.json`

The machine-readable package manifest layer.

Use it for:

- package lookup,
- composer package names,
- package family grouping,
- paired package discovery,
- canonical docs paths,
- related package hints.

### Commerce-specific skills

The initial Commerce skills live under:

- `../../.github/copilot/skills/commerce-ecosystem-navigation/SKILL.md`
- `../../.github/copilot/skills/commerce-owner-scoping-audit/SKILL.md`
- `../../.github/copilot/skills/commerce-filament-adapter-development/SKILL.md`
- `../../.github/copilot/skills/commerce-package-docs-truth-sync/SKILL.md`

These skills are designed to reduce ambiguity when an assistant needs to:

- choose the right package,
- audit owner safety,
- modify `filament-*` packages safely,
- keep package docs truthful and consistent.

## Recommended read order for assistants

1. [`../../CONTEXT.md`](../../CONTEXT.md)
2. [`../../CONTEXT-MAP.md`](../../CONTEXT-MAP.md)
3. [`package-manifests.json`](package-manifests.json)
4. the target package’s `../../packages/<pkg>/CONTEXT.md`
5. the target package’s `01-overview.md`
6. the target package’s installation, configuration, usage, and troubleshooting pages

## Manifest schema summary

Each manifest entry is intentionally small and retrieval-friendly.

| Field | Purpose |
| --- | --- |
| `composer` | Composer package name |
| `surface` | High-level package role such as `foundation`, `domain`, `filament`, `gateway`, `analytics`, or `bundle` |
| `family` | Package family grouping used in this monorepo |
| `paired_package` | Closest paired package, usually core ↔ Filament |
| `summary` | One-line ownership summary |
| `canonical_docs` | Canonical docs paths for overview, installation, configuration, usage, and troubleshooting |
| `related_packages` | Adjacent packages commonly read together |

## How to use the layer

### If you need to decide where a change belongs

- read `CONTEXT.md` first,
- read `CONTEXT-MAP.md` when the task crosses package boundaries,
- find the package entry in `package-manifests.json`,
- read the target package `CONTEXT.md`,
- confirm with the target package overview,
- only then move to config or implementation files.

### If you need to update admin UI

- find the paired `filament-*` package in the manifest,
- read both the core package overview and the Filament package overview,
- keep domain logic in the core package unless the change is truly UI-only.

### If you need to audit multitenancy or owner safety

- read `CONTEXT.md`,
- read `CONTEXT-MAP.md`,
- read `packages/commerce-support/docs/04-multi-tenancy.md`,
- then read the target package overview and configuration docs.

## Notes on legacy numbering

The package overview layer is now standardized, but some deep-dive docs still use older numbering or naming.

When filename conventions disagree with expectations, trust:

1. the package manifest,
2. the package overview,
3. the package docs index,
4. current source code.

## Related guides

- [`../index.md`](../index.md)
