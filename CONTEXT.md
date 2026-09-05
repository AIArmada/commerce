---
title: AIArmada Commerce AI Entry
status: current
kind: ai-entrypoint
---

# AIArmada Commerce AI Entry

Use this file first. It is intentionally short and optimized for routing an AI model to the right package and docs quickly.

## Read order

1. `PACKAGES.md` — 60-second package picker (all 67 packages, 1 line each)
2. `CONTEXT-MAP.md` — detailed routing rules, invariants, family map
3. `docs/ai/package-index.json` — machine-greppable index (keywords, triggers, pairs)
4. `docs/ai/package-manifests.json` — full manifest (composer names, canonical docs, related)
5. `packages/<target-package>/CONTEXT.md` — package entrypoint (use-when, surfaces, docs map)
6. `packages/<target-package>/docs/01-overview.md`

## Choose the owning package

- shared contracts, owner scoping, health checks, targeting, webhooks, money helpers → `packages/commerce-support`
- roles/permissions/scopes/impersonation core → `packages/authz`; admin UI → `packages/filament-authz`
- tenant aggregate, ownership transfer, current-org context → `packages/organizations`; join/invite flows → `packages/membership`
- person identity (names, titles, credentials, affiliations) → `packages/persons`
- addresses + country/area data → `packages/addressing`; contact points + social profiles → `packages/contacting`
- admin-only Filament work → the paired `packages/filament-*` package
- customers, products, pricing, inventory, tax, ticketing → the corresponding domain package
- seating maps/holds, event schedule/registrations, engagement reactions, surveys → `seating`, `events`, `engagement`, `feedback`
- promotions, vouchers, affiliates, affiliate-network, growth → the corresponding incentives package
- cart, checkout, orders, shipping, J&T → the corresponding checkout-flow package
- CHIP, cashier, cashier-chip, docs → the corresponding payments/documents package
- signals analytics, moderation blocks, bibliographic references → `signals`, `moderation`, `references`
- metapackage installation only → `packages/csuite`

## Non-negotiables

- `packages/*/docs/*.md` are canonical.
- `filament-*` packages are adapters, not domain owners.
- owner safety is never a UI-only concern.
- query-builder paths need explicit owner handling.
- money is minor units plus currency.

## Package context convention

- every `packages/*` root has `CONTEXT.md` with `Snapshot`, `Read next`, `Guardrails`, plus `Decide fast`, `Key surfaces`, `Docs map`
- each `CONTEXT.md` carries `keywords:` frontmatter for machine filtering
- use the package context before code search or edits
- if a task crosses core + Filament boundaries, read both package contexts

## Cross-package guides

- affiliate routing across `affiliates`, `vouchers`, `checkout`, and `filament-affiliates` → `docs/affiliates.md`
- full 1-line package picker → `PACKAGES.md`
- LLM entrypoint → `llms.txt`
