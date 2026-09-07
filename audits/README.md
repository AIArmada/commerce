# Monorepo Audit Index

Complete technical review of every package under `/packages/*`. Review-only phase for the audits themselves; the migration track has since been **implemented** (see below).

> **Migration track: COMPLETED 2026-09-07.** All 11 migration findings are settled — implemented, dropped, or corrected after independent verification. The record lives in [`migration-record.md`](migration-record.md) (per-package outcomes, evidence, commits, deployment gates). Per-package audit files describe **remaining work only**; each cleaned file points back to the record.

## Coverage

- **37 audit files**: 36 package audits + [`migration-record.md`](migration-record.md).
- **31 pairs** (main package + Filament adapter audited together as one unit).
- **5 standalones** (no Filament adapter): `checkout`, `csuite`, `membership`, `moderation`, `references`.
- Status: all complete, hardened, and migration-stripped. Every file contains the full section set with a `## Migration Impact` section.

### Pairs

Migration column: `Done` = implemented/dropped/corrected during the track (see record); `Yes` = still open; `No` = never required.

| Package | Filament Adapter | Migration | Breaking Changes | Highest Severity | Audit File |
|---|---|---|---|---|---|
| addressing | filament-addressing | Done | Yes | High | `addressing.md` |
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
| docs | filament-docs | Done | Yes | High | `docs.md` |
| engagement | filament-engagement | No | Yes | High | `engagement.md` |
| events | filament-events | No | Yes | High | `events.md` |
| feedback | filament-feedback | No | Yes | High | `feedback.md` |
| growth | filament-growth | Done | No | Medium | `growth.md` |
| inventory | filament-inventory | Done | Yes | Medium | `inventory.md` |
| jnt | filament-jnt | No | Yes | High | `jnt.md` |
| orders | filament-orders | No | Yes | Critical | `orders.md` |
| organizations | filament-organizations | Done | Yes | High | `organizations.md` |
| persons | filament-persons | Done | Yes | High | `persons.md` |
| pricing | filament-pricing | Done | No | High | `pricing.md` |
| products | filament-products | No | Yes | High | `products.md` |
| promotions | filament-promotions | Done | Yes | High | `promotions.md` |
| seating | filament-seating | No | No | High | `seating.md` |
| shipping | filament-shipping | Done | Yes | High | `shipping.md` |
| signals | filament-signals | Done | Yes | High | `signals.md` |
| tax | filament-tax | No | Yes | High | `tax.md` |
| ticketing | filament-ticketing | No | Yes | High | `ticketing.md` |
| vouchers | filament-vouchers | Done | Yes | High | `vouchers.md` |

### Standalones

| Package | Migration | Breaking Changes | Highest Severity | Audit File |
|---|---|---|---|---|
| checkout | No | Yes | Critical | `checkout.md` |
| csuite | No | No | Medium | `csuite.md` |
| membership | No | Yes | Medium | `membership.md` |
| moderation | No | Yes | High | `moderation.md` |
| references | No | No | High | `references.md` |

## Open Migrations (0)

All 11 migration packages are closed — 7 implemented, 4 dropped/corrected with evidence. See [`migration-record.md`](migration-record.md). `inventory.md` still rates Critical, but that is the open A1 serial-vocabulary *code* fix, not schema work.

## Highest-Risk Packages (Critical severity, 6)

`cashier`, `cashier-chip`, `checkout`, `chip`, `customers`, `orders`.

Dominant risk themes: cross-tenant isolation gaps in write paths (orders intake, customer resolver), payment amount/webhook integrity. (`inventory` dropped off this list: its serial Critical was falsified — slug storage on both sides — and implemented as a duplication cleanup.)

## Major Cross-Package Architectural Issues (remaining)

1. **Owner-scoping gaps (security).** Unscoped intake-dedup lookup in `orders` (`CreateOrder::findExistingIntake`), non-unique-per-owner `customers.email` + cross-tenant guest reuse in `CustomerResolver`, 57/64 `events` models on a bespoke scope framework instead of `HasOwner`. Settled: `addressing`/`persons` owner morphs shipped, `growth`/`signals` parity enforced, `tax` demoted (global scope applies). See `orders.md`, `customers.md`, `events.md`, `tax.md`.
2. **Identity concept split four ways.** `persons`, `customers`, `organizations`, and `events` each model identity/contact with no `person_id` bridge. Decide one canonical identity owner; see `persons.md`. (Persons tenancy question settled: shared-by-design.)
3. **Address lineage tripled.** `addressing` vs native columns in `customers`/`orders`, with zero adoption of `HasAddresses`. The blocker is gone — owner migration shipped — so consolidation can proceed; see `addressing.md`, `contacting.md`.
4. **Payments modeled three times.** `cashier` ↔ `cashier-chip` ↔ `chip` duplicate CHIP concepts; `checkout` duplicates chip's status mapper/payload builder and confirms payment without amount reconciliation. Collapse toward `cashier-chip`-canonical billing, `chip`-owned HTTP/verification, `cashier` as thin multiplexer; delete checkout's copies. See `cashier.md`, `cashier-chip.md`, `chip.md`, `checkout.md`.
5. **Pricing/promotions/vouchers (residual).** Dead promotion strategies deleted, BOGO promotion type removed (voucher-side BOGO mechanic untouched and live), voucher provenance canonicalized, broken cross-package class references fixed. Remaining: `pricing.ApplyPromotionalAdjustment` still bypasses the promotions domain; voucher validator hardening. See `pricing.md`, `vouchers.md`.
6. **Events ↔ ticketing ↔ seating forks.** 4 ticketing DTO/action forks live in `events`; `TicketableTypeRegistry` lives in the Filament adapter while core needs it; per-pass issuance loop and per-seat allocate loop under `lockForUpdate` need set-based rewrites. See `events.md`, `ticketing.md`, `seating.md`.
7. **Foundation boundary violations.** `commerce-support` owns authz-domain models (`Role`/`Permission`/`AuthzScope`) and the navigation engine is implemented twice; money APIs accept `float|string`; `products` `store_money_in_cents` toggle; `jnt` float money math. See `commerce-support.md`, `authz.md`, `products.md`, `jnt.md`.
8. **Filament adapters duplicating domain.** Snapshot dual-write (`filament-cart`), condition-application duplication, `CreateCustomer`/`UpdateCustomerProfile` parsing duplication, customer merge split across core/Filament, `GrowthStatsAggregator` N+1. See `cart.md`, `customers.md`, `growth.md`.
9. **Zero tests in nearly all packages.** Repo-root `tests/src/<Area>/` has partial coverage only. Test-absence findings are uniformly Medium per the rubric. Every audit lists highest-value first tests.
10. **Migration hygiene (residual).** Duplicate `000066` migration number in `events`, 9 tables in one `feedback` migration, inventory decimal columns (open migration above).

## Second pass (hardening) + migration track

- **Severity normalized, no inflation.** Critical is used only for breach-class security holes, corruption/data-loss risks, and broken integrity behavior (6 files, down from 10 — `addressing`/`persons` Criticals resolved by shipped owner morphs; `seating` was High in-file all along; `inventory` A1 falsified, see record).
- **False claims removed or corrected** (see hardening notes in prior revision; full list in `migration-record.md` deviations).
- **Migration track completed 2026-09-07.** 10 of 11 migration packages settled: 6 implemented (`pricing`, `organizations`, `addressing`, `vouchers`, `promotions`, + `growth` parity assertion), 4 dropped/corrected with evidence (`signals`, `shipping`, `persons`, `docs`). `inventory` untouched. Details, evidence, commit list (24 commits after base), and deployment gates in [`migration-record.md`](migration-record.md).
- **Reviewer spot-checks (all held).** Unscoped `findExistingIntake`, zero `HasOwner` in persons, wrong voucher import fixed to the real class, serial enum-vs-morph mismatch, empty-`getPages()` phantom chip resources, enum-removal consistency (zero `PromotionType::BuyXGetY` references repo-wide), guarded checkout-listener registration, boot-time owner-parity assertion.

## Recommended Overall Refactor Order (remaining work)

1. **Deployment gates first** (see `migration-record.md`): dev-only, no backfills — delete-and-rerun accepted; promotions migration timing; PHP 8.4 CI.
2. **Foundation:** `commerce-support` (move authz models out, dedupe navigation, strict money) + `authz` (narrow opt-outs, Octane-safe discovery).
3. **Critical isolation fixes (no migration):** `orders` intake scoping, `customers` email uniqueness + resolver scoping, `checkout` amount reconciliation + idempotency.
4. **Identity/address consolidation:** pick canonical owners; `HasAddresses` adoption now unblocked.
5. **Payments collapse:** `chip` (idempotency, webhook-client require, delete phantom resources) → `cashier-chip` (freeze renewal amounts, fix `period_key`) → `cashier` (thin multiplexer, fix 100× `formatAmount`) → `checkout` (use canonical contracts, delete duplicated mapper/builder).
6. **Pricing/vouchers residual + shipping/tax:** route pricing through promotions domain, voucher validator hardening.
7. **Events/ticketing/seating + affiliates/affiliate-network:** remove forks, move registry to core, set-based issuance, document programs-vs-offers boundary.
8. **Remainder:** `cart` snapshot consolidation, `products` config/policy fixes, `inventory` migration, `communications`, `engagement`, `feedback`, `docs`, `jnt`, standalones (`moderation` expiry sweep, `references` tenancy, `membership`, `csuite` bundle requires).
9. **Tests throughout:** add the listed first tests per package before touching risky code; keep `--parallel` per repo test guidelines.
