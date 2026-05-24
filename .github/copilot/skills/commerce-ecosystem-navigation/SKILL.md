---
name: commerce-ecosystem-navigation
description: >-
  Navigates the AIArmada Commerce monorepo. Activates when choosing the right Commerce package,
  deciding where a change belongs, mapping core versus filament package pairs, locating canonical
  package docs, identifying related packages, or when the user asks which package owns a feature,
  where to implement something, or what files to read first.
---

# Commerce Ecosystem Navigation

## When to Apply

Activate this skill when:

- a task spans multiple Commerce packages,
- you need to decide where a change belongs,
- you need to identify the owning package for a feature,
- you need to map a core package to its paired `filament-*` package,
- you need the shortest reading path before making a change.

## Read First

1. `CONTEXT.md`
2. `docs/ai/package-manifests.json`
3. `docs/index.md`
4. the target package's `01-overview.md`

## Workflow

1. Classify the task surface:
   - domain logic,
   - Filament/admin UI,
   - gateway adapter,
   - checkout orchestration,
   - analytics/experimentation,
   - bundle selection.
2. Use the manifest to pick the owning package.
3. Only add the paired `filament-*` package when the change is truly UI/admin-facing.
4. Name the related packages that might be affected.
5. Point to the canonical docs to read next.
6. Suggest the most likely package-scoped tests or verification path.

## Routing Heuristics

| Task type | Start in |
| --- | --- |
| shared owner rules, payment contracts, targeting engine | `commerce-support` |
| model/service/business rule changes | owning core package |
| resources, pages, widgets, panel plugin config | paired `filament-*` package |
| CHIP direct payment or payout integration | `chip` |
| gateway-neutral billing | `cashier` |
| CHIP recurring billing | `cashier-chip` |
| analytics, reports, alerts | `signals` or `filament-signals` |
| experiments and winner metrics | `growth` or `filament-growth` |
| marketplace affiliate offers and sites | `affiliate-network` or `filament-affiliate-network` |
| shipping abstraction | `shipping` |
| J&T-specific execution | `jnt` |
| docs, PDFs, numbering, e-invoices | `docs` or `filament-docs` |

## Expected Output

When you use this skill, give a short answer that names:

- the owning package,
- the paired package if relevant,
- the related packages to read next,
- the canonical docs paths,
- any package-boundary pitfalls.

## Common Pitfalls

- Editing a `filament-*` package when the real change belongs in the core package
- Changing a domain package for what is actually only a panel/widget concern
- Assuming checkout owns shipping, pricing, or order persistence instead of orchestrating them
- Missing `commerce-support` when the real issue is owner scoping, shared contracts, or targeting
