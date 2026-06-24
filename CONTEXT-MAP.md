---
title: AIArmada Commerce Context Map
status: current
kind: repository-map
---

# AIArmada Commerce Context Map

This file is the detailed repository map for AI assistants and humans working in the `commerce` monorepo.

For the fastest AI-oriented entrypoint, start with `CONTEXT.md` and then come here.

## Fast read order

1. `CONTEXT.md`
2. `docs/ai/package-manifests.json`
3. `packages/<pkg>/CONTEXT.md`
4. `packages/<pkg>/docs/01-overview.md`
5. the relevant installation, configuration, usage, and troubleshooting pages for the target package

## Monorepo shape

The monorepo is organized around a few stable layers:

- **Foundation** — shared primitives used by many packages (`commerce-support`)
- **Core domain packages** — own models, services, business rules, and integration contracts
- **Filament packages** — admin and operator adapters for the paired domain packages
- **Gateway or adapter packages** — connect Commerce to external services such as CHIP or J&T
- **Analytics and experimentation** — behavioral analytics and growth tooling (`signals`, `growth`)
- **Bundle/metapackage** — preselected package installation bundle (`csuite` / `aiarmada/commerce`)

## Non-negotiable invariants

### 1. Package docs are canonical

`packages/*/docs/*.md` are the canonical source of truth for package behavior, configuration, extension points, and admin UI.

If code and docs disagree, update the docs in the same pass.

### 2. Core domain logic lives in core packages

If the change affects:

- models,
- persistence,
- service logic,
- state transitions,
- calculations,
- event dispatching,
- contracts,
- gateway integrations,
- ownership rules,

then the owning **core package** is the primary place to change.

### 3. Filament packages are adapters, not domain owners

`filament-*` packages should own:

- resources,
- pages,
- widgets,
- tables,
- forms,
- infolists,
- admin-focused actions,
- panel registration,

but they should **not** become the source of truth for domain calculations or persistence rules.

### 4. Owner scoping comes from `commerce-support`

Owner-boundary rules are centralized in `commerce-support`.

Treat these rules as stable:

- tenant-owned records are owner-scoped,
- `owner = null` means **global-only**, not “all owners”,
- missing owner context is **not** equivalent to global access,
- explicit global or cross-owner access should be deliberate and greppable,
- UI filtering is not authorization.

### 5. Server-side validation is mandatory

Relationship selects, filtered tables, and Filament options improve usability, but any submitted IDs still need owner-safe server-side validation.

### 6. Query-builder paths need explicit owner handling

When a package uses `DB::table(...)` or other query-builder-only paths, Eloquent global scopes do not apply automatically. Those paths must be owner-scoped deliberately.

### 7. No DB-level foreign key constraints or cascades

The monorepo contract is application-level integrity and cascading behavior, not DB-level constraints.

### 8. Money is minor units plus currency

Treat money as integer minor units with an explicit currency code.

### 9. Package independence matters

Packages should work standalone when possible and integrate conditionally via `class_exists()` or explicit optional dependencies.

## Package routing heuristics

Use these routing rules before editing code or docs.

| If the task is about... | Start in... | Then check... |
| --- | --- | --- |
| affiliate routing across package boundaries | `docs/affiliates.md` | `affiliates`, `vouchers`, `checkout`, `filament-affiliates` |
| shared owner rules, payment contracts, targeting engine, shared cache/file helpers | `commerce-support` | dependent package docs |
| authz scopes, panel/page/widget authorization, impersonation | `filament-authz` | paired Filament package |
| outbound, inbound, inbox, templates, preferences, and suppression records | `communications` | `filament-communications`, `contacting`, `commerce-support` |
| customer records and customer admin | `customers` | `filament-customers` |
| catalog records and product admin | `products` | `filament-products` |
| stock, allocations, warehouses, costing | `inventory` | `filament-inventory` |
| price lists, tiers, pricing settings | `pricing` | `filament-pricing` |
| tax zones, rates, exemptions | `tax` | `filament-tax` |
| promotions and discount campaigns | `promotions` | `filament-promotions` and `pricing` |
| vouchers, voucher wallets, manual redemption | `vouchers` | `filament-vouchers` and `cart` |
| affiliates, commissions, payouts, fraud signals | `affiliates` | `filament-affiliates` |
| affiliate marketplace, offers, sites, merchant network | `affiliate-network` | `filament-affiliate-network` |
| experimentation and winner metrics | `growth` | `filament-growth` and `signals` |
| carts, items, cart conditions | `cart` | `filament-cart` and `checkout` |
| checkout orchestration | `checkout` | `cart`, `orders`, `shipping`, payment packages |
| orders, refunds, invoices, state transitions | `orders` | `filament-orders`, `shipping`, `docs` |
| carrier-agnostic shipping | `shipping` | `filament-shipping` |
| J&T-specific shipping execution | `jnt` | `filament-jnt` and `shipping` |
| direct CHIP gateway collect/send | `chip` | `filament-chip`, `checkout`, `cashier-chip` |
| unified multi-gateway billing | `cashier` | `filament-cashier`, `cashier-chip` |
| CHIP recurring billing | `cashier-chip` | `filament-cashier-chip`, `chip`, `cashier` |
| business documents, PDFs, numbering, e-invoices | `docs` | `filament-docs`, `orders`, `checkout` |
| event registrations and venues | `events` | `filament-events` |
| blocks, bans, and moderation actions | `moderation` | `events`, `commerce-support` |
| reference sources, slugs, and hierarchical citation parts | `references` | `events`, `commerce-support` |
| analytics, alerts, reports, tracker ingestion | `signals` | `filament-signals`, `growth` |
| bundle installation and package selection | `csuite` | `docs/index.md`, `docs/ai/package-manifests.json` |

## Family map

- **Foundation**: `commerce-support`, `filament-authz`
- **Communications**: `communications`, `filament-communications`
- **Governance and safety**: `moderation`
- **Knowledge and references**: `references`
- **Catalog and identity**: `customers`, `filament-customers`, `products`, `filament-products`, `inventory`, `filament-inventory`, `pricing`, `filament-pricing`, `tax`, `filament-tax`
- **Growth and incentives**: `promotions`, `filament-promotions`, `vouchers`, `filament-vouchers`, `affiliates`, `filament-affiliates`, `affiliate-network`, `filament-affiliate-network`, `growth`, `filament-growth`
- **Checkout flow**: `cart`, `filament-cart`, `checkout`, `orders`, `filament-orders`, `shipping`, `filament-shipping`, `jnt`, `filament-jnt`
- **Payments and documents**: `chip`, `filament-chip`, `cashier`, `filament-cashier`, `cashier-chip`, `filament-cashier-chip`, `docs`, `filament-docs`
- **Analytics and events**: `events`, `filament-events`, `signals`, `filament-signals`
- **Bundle**: `csuite`

## Editing checklist

Before shipping a change:

1. Identify the owning package.
2. Read its overview, install, config, and troubleshooting docs.
3. Check whether the paired Filament package also needs updates.
4. Confirm owner-scoping and security semantics.
5. Run package-scoped verification instead of repo-wide sweeps.
6. Update docs when the public surface, ownership, or config shape changes.

## AI retrieval layer

The AI-oriented retrieval layer lives in:

- `CONTEXT.md`
- `CONTEXT-MAP.md`
- `docs/affiliates.md`
- `docs/ai/01-overview.md`
- `docs/ai/package-manifests.json`
- `packages/*/CONTEXT.md`
- `.github/copilot/skills/commerce-*/SKILL.md`

Use the root `CONTEXT.md` as the short AI dispatch file.
Use this map for the detailed repo-wide routing rules and invariants.
Use the manifest to find the canonical docs for a package.
Use package-level `CONTEXT.md` files before deep-diving into code.
Use the skills when the task is specifically about package routing, owner-scope auditing, Filament adapter work, or package-doc truth syncing.
