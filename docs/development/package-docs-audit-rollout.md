---
title: Package Docs Audit & AI Rollout
status: current
---

# Package Docs Audit & AI Rollout

Use this guide to audit Commerce package docs for truth first, normalize them to a consistent structure, and prepare the monorepo for upcoming AI-oriented documentation work.

## Goal

Make `packages/*/docs/*.md` accurate, consistent, and easy for both humans and AI systems to navigate.

Success means:

- package docs are more trustworthy than codebase guesswork,
- package boundaries are explicit,
- security and owner-scoping rules are stated correctly,
- related package docs cross-link cleanly,
- the repo is ready for later AI manifests, package maps, and custom skills.

## Working rules

1. Truth first, polish second.
2. `packages/*/docs/*.md` stay the canonical source for package behavior, configuration, extension points, and admin UI.
3. Root `docs/*.md` files explain cross-package behavior, onboarding, and ecosystem context. They should not replace package docs.
4. If docs conflict with code, config, or tests, update the docs in the same pass.
5. Normalize structure during package audits. Do not do a repo-wide rename-only sweep.

## Standard package docs template

Every package should converge on the same baseline file set.

### Required baseline files

| File | Purpose |
| --- | --- |
| `01-overview.md` | Package boundary, ownership, paired packages, main concepts, and what to read next |
| `02-installation.md` | Requirements, install steps, publish/setup steps, and verification |
| `03-configuration.md` | Real config keys, defaults, environment variables, and feature flags |
| `04-usage.md` | Common tasks, copy-paste examples, end-to-end flows, and extension seams |
| `99-troubleshooting.md` | Real symptoms, likely causes, fixes, and how to verify |

### Recommended optional files for core and domain packages

Use these only when the package truly has the extra surface area.

- `05-models-reference.md`
- `05-contracts-dtos.md`
- `06-integrations.md`
- `06-services.md`
- `07-events.md`
- `07-multi-tenancy.md`
- `08-testing.md`
- `08-api-reference.md`

### Recommended optional files for Filament packages

- `05-resources.md`
- `06-pages-widgets.md`
- `06-widgets.md`
- `07-actions-reference.md`
- `07-customization.md`
- `08-multi-tenancy.md`
- `08-testing.md`

Keep numbering predictable:

- installation stays at `02`,
- configuration stays at `03`,
- usage stays at `04`,
- troubleshooting stays at `99`,
- optional deep dives are added after `04` in the order readers are likely to need them.

## Required section structure inside each file

The filenames should be consistent, and the internal headings should be consistent too.

### `01-overview.md`

Every package overview should include:

- `## Purpose`
- `## What this package owns`
- `## What this package does not own`
- `## Related packages`
- `## Main models services or surfaces`
- `## Owner scoping and security notes` when relevant
- `## Read next`

For AI readability, the `What this package owns` and `What this package does not own` sections are mandatory.

### `02-installation.md`

Should include:

- requirements,
- Composer install command,
- publish or install commands,
- migration or setup notes,
- integration prerequisites,
- one clear verification step,
- links to the next docs to read.

### `03-configuration.md`

Should include:

- the actual config file shape,
- real defaults,
- environment variables,
- a section per config group,
- notes on which features each key actually controls.

When a package follows the standard config order, keep the doc in the same order:

- Database
- Credentials or API
- Defaults
- Features or Behavior
- Integrations
- HTTP
- Webhooks
- Cache
- Logging

For Filament packages, keep Filament-specific config sections in this order:

- Navigation
- Tables
- Features
- Resources

### `04-usage.md`

Should include:

- the top 3 to 5 most common tasks,
- copy-paste-ready examples,
- expected outputs or side effects,
- links to deeper docs for advanced flows,
- notes on extension seams such as events, contracts, hooks, or paired packages.

### `99-troubleshooting.md`

Each troubleshooting entry should follow this pattern:

- symptom,
- likely cause,
- fix,
- how to verify the fix.

Avoid vague FAQ filler. Troubleshooting should reflect real failure modes seen in code, tests, or support/audit work.

## Known naming and structure drift to normalize

These are good early examples of the drift this rollout should fix:

- `packages/docs/docs/00-overview.md` should converge on `01-overview.md`
- `packages/cashier/docs/01-getting-started.md` should converge on the standard overview plus installation split
- `packages/filament-cart/docs/02-resources.md`, `03-installation.md`, and `04-configuration.md` should converge on the common ordering
- `packages/chip/docs/99-trouble.md` should converge on `99-troubleshooting.md`

Fix these during the relevant package waves rather than in a standalone rename-only PR.

## Practical audit checklist

Use this checklist package by package.

### 1. Structure audit

- [ ] Frontmatter exists and the title matches the page purpose
- [ ] Required baseline files exist or there is a documented reason a file is not needed yet
- [ ] Filenames follow the standard numbering pattern
- [ ] Optional files are ordered by reader need, not by implementation history
- [ ] Relative links resolve to current docs, not archived material

### 2. Boundary audit

- [ ] `01-overview.md` clearly states what the package owns
- [ ] `01-overview.md` clearly states what the package does not own
- [ ] Paired packages are named explicitly
- [ ] Cross-package docs point back to the canonical package docs instead of duplicating details
- [ ] Domain packages are not described as owning Filament concerns
- [ ] Filament packages are described as adapters, not domain owners

### 3. Truth audit against code

- [ ] Documented config keys exist in the package config
- [ ] Documented config keys have real read paths in the codebase
- [ ] Installation steps reference real commands, tags, and setup flows
- [ ] Usage examples match current public APIs
- [ ] Model, resource, widget, page, or service lists match the current source tree
- [ ] Event names, DTOs, and integration terms match current code
- [ ] Documented defaults match current config defaults
- [ ] Troubleshooting steps describe real current behavior

### 4. Security and owner-scoping audit

- [ ] Owner terminology matches `commerce-support`
- [ ] Explicit global context is described correctly when relevant
- [ ] Missing owner context is not described as equivalent to global access
- [ ] `DB::table()` caveats are documented when raw query builders appear in the package
- [ ] Filament docs state that option scoping is not authorization when the package accepts submitted IDs
- [ ] Jobs, commands, exports, reports, health checks, or webhooks use explicit owner context language where relevant

### 5. AI-readiness audit

- [ ] Overview pages are concise enough to retrieve well
- [ ] Important boundaries appear near the top of the file
- [ ] Main nouns are stable and used consistently
- [ ] “Read next” sections point to the most likely follow-up pages
- [ ] There is no stale historical language presented as current behavior
- [ ] The doc answers “where should a change go?” without requiring source spelunking

### 6. Exit criteria

- [ ] The package follows the standard doc structure closely enough to reuse for future AI docs
- [ ] The package boundary is explicit and current
- [ ] Config, usage, and troubleshooting pages are truthful
- [ ] Root cross-package guides are updated if the package boundary changed
- [ ] The package is ready for later machine-readable manifests or AI summary layers

## Severity triage during the audit

Use consistent severity so the rollout does not get bogged down in typography while ownership or security docs are still wrong.

### Blocker

- wrong package ownership,
- wrong security or owner-scoping semantics,
- wrong config key names,
- broken installation or publish steps,
- stale examples that would cause a user or AI to change the wrong package.

### Major

- missing required baseline file,
- missing paired-package links,
- stale model, resource, or widget inventory,
- usage docs that no longer reflect the public surface.

### Minor

- inconsistent headings,
- missing “Read next” sections,
- weak troubleshooting wording,
- numbering drift that does not yet mislead readers.

## Rollout workflow per package

Use the same sequence every time.

1. Read `01-overview.md`, `03-configuration.md`, `04-usage.md`, and `99-troubleshooting.md` first.
2. Compare them against the package config, source tree, and tests.
3. Fix blocker and major truth issues before improving wording.
4. Normalize file names and section structure while already in the package.
5. Update root-level cross-package docs only if the package boundary or recommended reading path changed.

Keep PRs reviewable:

- prefer one seam or one wave slice per PR,
- pair a domain package with its Filament package when the docs cross-reference each other heavily,
- avoid giant repo-wide doc sweeps.

## Rollout order for Commerce

The rollout order below is designed to make later package audits easier. Earlier waves define terms and boundaries that later waves depend on.

### Wave 0 — root docs and shared rules

Handle these before package sweeps so the repo-level story stays aligned:

- `docs/index.md`
- `docs/04-support-utilities.md`
- `docs/06-deployment.md`
- `docs/affiliates.md`
- `docs/tenancy-evaluation-report.md`
- `docs/development/package-docs-audit-rollout.md`

### Wave 1 — foundation and shared control surfaces

Start with the shared seams every other package depends on.

1. `commerce-support`
2. `filament-authz`

### Wave 2 — catalog and identity

These packages define the nouns that many other packages reference.

3. `customers`
4. `filament-customers`
5. `products`
6. `filament-products`
7. `inventory`
8. `filament-inventory`
9. `pricing`
10. `filament-pricing`
11. `tax`
12. `filament-tax`

Suggested PR slices:

- customers pair,
- products plus inventory and their Filament pairs,
- pricing plus tax and their Filament pairs.

### Wave 3 — promotions, vouchers, affiliates, and growth

Audit the packages that shape acquisition, incentives, and program logic.

13. `promotions`
14. `filament-promotions`
15. `vouchers`
16. `filament-vouchers`
17. `affiliates`
18. `filament-affiliates`
19. `affiliate-network`
20. `filament-affiliate-network`
21. `growth`
22. `filament-growth`

Suggested PR slices:

- promotions plus vouchers,
- affiliates plus affiliate-network,
- growth plus any remaining Filament cleanup for the wave.

### Wave 4 — cart, checkout, orders, shipping, and fulfilment

This wave covers the main transactional flow from basket to delivery.

23. `cart`
24. `filament-cart`
25. `checkout`
26. `orders`
27. `filament-orders`
28. `shipping`
29. `filament-shipping`
30. `jnt`
31. `filament-jnt`

Suggested PR slices:

- cart plus checkout,
- orders plus shipping,
- jnt plus remaining Filament adjustments.

### Wave 5 — payments and document output

Audit gateway and billing/document packages after the core transaction flow is stable.

32. `chip`
33. `filament-chip`
34. `cashier`
35. `filament-cashier`
36. `cashier-chip`
37. `filament-cashier-chip`
38. `docs`
39. `filament-docs`

Suggested PR slices:

- chip pair,
- cashier plus cashier-chip and their Filament pairs,
- docs plus filament-docs.

### Wave 6 — events and signals

These packages sit on top of other package truths, so they come later.

40. `events`
41. `filament-events`
42. `signals`
43. `filament-signals`

### Wave 7 — meta and bundle docs

Finish with the bundle package after the underlying package docs are truthful.

44. `csuite`

## Practical outcome of this rollout

Once the waves above are complete, Commerce should be ready for the next documentation layer:

- AI-oriented package manifests,
- ecosystem navigation docs,
- package-selection and owner-safety skills,
- cross-package task maps,
- safer retrieval for automated coding assistants.

That later AI layer should be generated from truthful package docs, not used as a substitute for them.