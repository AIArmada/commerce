# Monorepo Audit Index

Complete technical review of every package under `/packages/*`. Review-only phase; no source code was modified.

Two passes completed: (1) initial per-package audits, (2) hardening pass — every Critical/High finding re-verified against source, severity normalized against a strict rubric, false claims corrected or removed, shallow files deepened, headings/sections normalized. See [Second pass](#second-pass-hardening) for what changed.

## Coverage

- **36 audit files**, one per main package / pair.
- **31 pairs** (main package + Filament adapter audited together as one unit).
- **5 standalones** (no Filament adapter): `checkout`, `csuite`, `membership`, `moderation`, `references`.
- Status: all complete, all hardened. Every file contains the full section set with a `## Migration Impact` section carrying an exact `**Migration Required: YES/NO**` flag.

### Pairs

| Package | Filament Adapter | Migration | Breaking Changes | Highest Severity | Audit File |
|---|---|---|---|---|---|
| addressing | filament-addressing | Yes | Yes | Critical | `addressing.md` |
| affiliate-network | filament-affiliate-network | No | Yes | High | `affiliate-network.md` |
| affiliates | filament-affiliates | No | Yes | High | `affiliates.md` |
| authz | filament-authz | No | Yes | High | `authz.md` |
| cart | filament-cart | No | Yes | High | `cart.md` |
| cashier | filament-cashier | No | Yes | Critical | `cashier.md` |
| cashier-chip | filament-cashier-chip | No | Yes | Critical | `cashier-chip.md` |
| chip | filament-chip | No | Yes | Critical | `chip.md` |
| commerce-support | filament-commerce-support | No | Yes | High | `commerce-support.md` |
| communications | filament-communications | No | Yes | High | `communications.md` |
| contacting | filament-contacting | No | Yes | High | `contacting.md` |
| customers | filament-customers | No | Yes | Critical | `customers.md` |
| docs | filament-docs | Yes | Yes | High | `docs.md` |
| engagement | filament-engagement | No | Yes | High | `engagement.md` |
| events | filament-events | No | Yes | High | `events.md` |
| feedback | filament-feedback | No | Yes | High | `feedback.md` |
| growth | filament-growth | Yes | No | Medium | `growth.md` |
| inventory | filament-inventory | Yes | Yes | Critical | `inventory.md` |
| jnt | filament-jnt | No | Yes | High | `jnt.md` |
| orders | filament-orders | No | Yes | Critical | `orders.md` |
| organizations | filament-organizations | Yes | Yes | High | `organizations.md` |
| persons | filament-persons | Yes | Yes | Critical | `persons.md` |
| pricing | filament-pricing | Yes | No | High | `pricing.md` |
| products | filament-products | No | Yes | High | `products.md` |
| promotions | filament-promotions | Yes | Yes | High | `promotions.md` |
| seating | filament-seating | No | No | Critical | `seating.md` |
| shipping | filament-shipping | Yes | Yes | High | `shipping.md` |
| signals | filament-signals | Yes | Yes | High | `signals.md` |
| tax | filament-tax | No | Yes | High | `tax.md` |
| ticketing | filament-ticketing | No | Yes | High | `ticketing.md` |
| vouchers | filament-vouchers | Yes | Yes | High | `vouchers.md` |

### Standalones

| Package | Migration | Breaking Changes | Highest Severity | Audit File |
|---|---|---|---|---|
| checkout | No | Yes | Critical | `checkout.md` |
| csuite | No | No | Medium | `csuite.md` |
| membership | No | Yes | Medium | `membership.md` |
| moderation | No | Yes | High | `moderation.md` |
| references | No | No | High | `references.md` |

## Packages Requiring Migrations (11)

- `addressing.md` — owner morphs on `addresses`/`addressables`/`address_snapshots`, snapshot composite index; reference tables stay global.
- `docs.md` — `doc_workflows.payload` json→jsonb (new migration, not editing the shipped one).
- `growth.md` — index-only: replace `unique(owner_scope,slug)` with owner-tuple key + assignment uniqueness key.
- `inventory.md` — dedicated `reservation_group_id` migration; drop decimal quantity columns.
- `organizations.md` — `unique(slug)`, `unique(organization_id,user_id)`, `index(organization_id,role)`; no column changes.
- `persons.md` — nullable owner morphs on 3 assignment tables, drop wrong `titles` unique, covering/partial uniques.
- `pricing.md` — additive `price_tiers` composite index only.
- `promotions.md` — data-only: deactivate `buy_x_get_y` rows (strategy is dead/broken).
- `shipping.md` — narrowly scoped: `nullableUuidMorphs('owner')` on `shipping_rates` only.
- `signals.md` — unique `['tracked_property_id','idempotency_key']`.
- `vouchers.md` — promotion-source backfill, drop `voucher_assignments`/`voucher_transactions`, drop redundant `code` index.

Every other package: code-only refactors, no schema or data migration.

## Highest-Risk Packages (Critical severity, 10)

`addressing`, `cashier`, `cashier-chip`, `checkout`, `chip`, `customers`, `inventory`, `orders`, `persons`, `seating`.

Dominant risk themes: cross-tenant isolation gaps (unscoped reads/writes), payment amount/webhook integrity, dead-but-reachable promotion strategies, duplicated identity/address ownership, concurrency-unsafe seat allocation.

## Major Cross-Package Architectural Issues

1. **Owner-scoping gaps (security).** Unscoped intake-dedup lookup in `orders` (`CreateOrder::findExistingIntake`), non-unique-per-owner `customers.email` + cross-tenant guest reuse in `CustomerResolver`, declared-no-scoping PII in `addressing`, unscoped polymorphic assignments in `persons`, 57/64 `events` models on a bespoke scope framework instead of `HasOwner`, `growth`/`signals` owner-mode agreement unenforced. `tax` calculator was demoted on re-check: the global `OwnerScope` still applies, so proven impact is silent zero-tax reads, not a proven leak. See `orders.md`, `customers.md`, `addressing.md`, `persons.md`, `events.md`, `growth.md`, `tax.md`.
2. **Identity concept split four ways.** `persons`, `customers`, `organizations`, and `events` each model identity/contact with no `person_id` bridge. Decide one canonical identity owner; see `persons.md`.
3. **Address lineage tripled.** `addressing` vs native columns in `customers`/`orders`, with zero adoption of `HasAddresses`. Consolidate on `addressing` (after its owner migration ships — adoption is blocked until then); see `addressing.md`, `contacting.md`.
4. **Payments modeled three times.** `cashier` ↔ `cashier-chip` ↔ `chip` duplicate CHIP concepts (HTTP, verification, billing); `checkout` duplicates chip's status mapper/payload builder and confirms payment without amount reconciliation. Collapse toward `cashier-chip`-canonical billing, `chip`-owned HTTP/verification, `cashier` as thin multiplexer; delete checkout's copies. Hardening corrected two overstatements here: no second `chip_webhooks` table exists (single `webhook_calls` store), and chip's verify-accept-all path is non-production only. See `cashier.md`, `cashier-chip.md`, `chip.md`, `checkout.md`.
5. **Pricing/promotions/vouchers bypass and rot.** `pricing.ApplyPromotionalAdjustment` reimplements promotion logic bypassing the promotions domain (demoted Critical→High: duplicated-domain defect, no data loss); the promotion strategy subsystem is dead with zero callers (demoted Critical→High: trap, not live breakage) and `BuyXGetY` discounts 0; `filament-cart` imports a nonexistent voucher action class and the affiliates `VoucherBridge` checks a nonexistent model (both verified, 1-line fixes). See `pricing.md`, `promotions.md`, `vouchers.md`.
6. **Events ↔ ticketing ↔ seating forks.** 4 ticketing DTO/action forks live in `events` (verified divergent, not byte-identical); `TicketableTypeRegistry` lives in the Filament adapter while core needs it; per-pass issuance loop and per-seat allocate loop under `lockForUpdate` need set-based rewrites; `events` venue overlap was demoted (address delegation, not a fork). See `events.md`, `ticketing.md`, `seating.md`.
7. **Foundation boundary violations.** `commerce-support` owns authz-domain models (`Role`/`Permission`/`AuthzScope`) and the navigation engine is implemented twice; money APIs accept `float|string`; `products` adds a `store_money_in_cents` toggle against the minor-units rule (verified latent, not active corruption); `jnt` does float money math. Move authz models to `authz`, dedupe navigation, normalize money. See `commerce-support.md`, `authz.md`, `products.md`, `jnt.md`.
8. **Filament adapters duplicating domain.** Snapshot dual-write (`filament-cart`), condition-application duplication, `CreateCustomer`/`UpdateCustomerProfile` parsing duplication, customer merge split across core/Filament, `GrowthStatsAggregator` N+1 over signals tables. Move domain behavior into main packages; keep adapters as UI shells. See `cart.md`, `customers.md`, `growth.md`.
9. **Zero tests in nearly all packages.** Repo-root `tests/src/<Area>/` has partial coverage only (Jnt strongest, Authz core weakest relative to risk: 3 root tests). Test-absence findings are uniformly rated Medium per the rubric (testability gap, however severe the untested path). Every audit lists highest-value first tests; add failure-path, authorization, and boundary tests before refactoring risky packages.
10. **Migration hygiene.** Duplicate `000066` migration number in `events`, 9 tables in one `feedback` migration, decimal write-dead columns in `inventory`, Octane-unsafe static/singleton state in `feedback` and `filament-authz`. See respective files.

## Second pass (hardening)

- **Severity normalized, no inflation.** Critical is now used only for breach-class security holes, corruption/data-loss risks, and broken integrity behavior (10 files). Demoted ~20 findings: test absence Critical/High→Medium across the board; dead code with zero runtime effect High→Medium (`promotions` A3/A4, `inventory` A2/A3, `chip` L-1); unproven leak claims Critical→High where a global scope still applies (`tax` A-1, `pricing` F1); duplicate-count pointers (`cashier-chip` S-1); `events` A3 fork→delegation (High→Medium); `feedback` C1 already-guarded (Medium→Low); `growth` A2 bypass→redundant-strip (High→Low); `products` config-dead Critical→High; `customers` name/phone duplication High→Medium.
- **False claims removed or corrected.** `inventory` BackorderStatus-enum sub-claim (no such file exists); `products` "unused" Customer import (verified used behind `class_exists`); `customers` "unused" `table_prefix` (fallback reads exist); `cashier-chip`/`cashier` formatAmount "near-identical" (they differ — cashier's has the 100× bug, cashier-chip's is correct); `chip` second webhook table (does not exist) and production accept-all (non-prod only); `ticketing` `pass_no_prefix` config drift (no drift — code and config agree); `growth` "signals defaults owner-on" (verified both default off); `orders` "dead" status config and health check (both have readers/docs refs — narrowed, not deleted).
- **Migration flags flipped NO→YES (4).** `growth` (index-only slug/assignment keys), `signals` (idempotency unique), `docs` (payload json→jsonb), `shipping` (`shipping_rates` owner morphs). Total migration packages: 7 → 11.
- **Depth + structure.** `persons`/`organizations`/`addressing`/`contacting` deepened (~100 → 156–184 lines) with re-audit findings and re-check notes; all 36 files use a clean `## Migration Impact` heading with the exact flag block; no backward-compat shims recommended anywhere.
- **Reviewer spot-checks (all hold).** `orders` unscoped `findExistingIntake` (`CreateOrder.php:216-219`); zero `HasOwner` hits in `persons/src`; wrong `FilamentVouchers\Extensions\CartVoucherActions` import in `filament-cart` `ViewCart.php:12`; `SerialStatus` enum refs in `filament-inventory` serial UI; empty-`getPages()` phantom resources in `filament-chip` (`AuditLog`, `ComplianceReport`, `FraudReview`, `PaymentLink`, …).

## Recommended Overall Refactor Order

1. **Foundation:** `commerce-support` (move authz models out, dedupe navigation, strict money) + `authz` (narrow opt-outs, Octane-safe discovery).
2. **Critical isolation fixes (no migration):** `orders` intake scoping, `customers` email uniqueness + resolver scoping, `checkout` amount reconciliation + idempotency.
3. **Identity/address decisions:** `persons` + `organizations` + `addressing` + `contacting` — pick canonical owners, apply migrations (YES for persons/organizations/addressing), migrate consumers. Adoption of `HasAddresses` stays blocked until the addressing owner migration ships.
4. **Payments collapse:** `chip` (idempotency, webhook-client require, delete phantom resources) → `cashier-chip` (freeze renewal amounts, fix `period_key`) → `cashier` (thin multiplexer, fix 100× `formatAmount`) → `checkout` (use canonical contracts, delete duplicated mapper/builder).
5. **Pricing/promotions/vouchers + shipping/tax:** kill dead strategies (data migration), route pricing through promotions domain, fix broken cross-package class references, apply voucher/shipping migrations.
6. **Events/ticketing/seating + affiliates/affiliate-network:** remove forks, move registry to core, set-based issuance, document programs-vs-offers boundary.
7. **Remainder:** `cart` snapshot consolidation, `products` config/policy fixes, `inventory` migration, `communications`, `engagement`, `feedback`, `growth`/`signals` (index migration + owner-mode contract), `docs` (payload migration), `jnt`, standalones (`moderation` expiry sweep, `references` tenancy, `membership`, `csuite` bundle requires).
8. **Tests throughout:** add the listed first tests per package before touching risky code; keep `--parallel` per repo test guidelines.
