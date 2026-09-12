# Monorepo Audit Index

Complete technical review of every package under `/packages/*`. Review-only phase for the audits themselves; the migration track has since been **implemented** (see below).

> **Migration track: COMPLETED 2026-09-07.** All 11 migration findings are settled — implemented, dropped, or corrected after independent verification. The record lives in [`migration-record.md`](migration-record.md) (per-package outcomes, evidence, commits, deployment gates). Per-package audit files carry DONE verdicts with residual notes; post-track schema work is recorded in `migration-record.md` as deviations.

## Coverage

- **39 audit files**: 36 package audits + [`migration-record.md`](migration-record.md) + [`code-fixes-record.md`](code-fixes-record.md) + this index.
- **31 pairs** (main package + Filament adapter audited together as one unit).
- **5 standalones** (no Filament adapter): `checkout`, `csuite`, `membership`, `moderation`, `references`.
- Status: all complete, hardened, and migration-stripped. Every file contains the full section set with a `## Migration Impact` section.

### Pairs

Migration column: `Done` = implemented/dropped/corrected during the track (see record); `Yes` = still open; `No` = never required.

| Package | Filament Adapter | Migration | Breaking Changes | Highest Severity | Audit File | Status |
|---|---|---|---|---|---|
| addressing | filament-addressing | Done | Yes | High | `addressing.md` | Done |
| affiliate-network | filament-affiliate-network | No | Yes | High | `affiliate-network.md` | Done |
| affiliates | filament-affiliates | No | Yes | High | `affiliates.md` | Done |
| authz | filament-authz | No | Yes | Medium | `authz.md` | Done |
| cart | filament-cart | No | Yes | High | `cart.md` | Done |
| cashier | filament-cashier | No | Yes | Critical | `cashier.md` | Done |
| cashier-chip | filament-cashier-chip | No | Yes | High | `cashier-chip.md` | Done |
| chip | filament-chip | No | Yes | High | `chip.md` | Done |
| commerce-support | filament-commerce-support | No | No | Low | `commerce-support.md` | Done |
| communications | filament-communications | No | Yes | High | `communications.md` | Done |
| contacting | filament-contacting | No | Yes | High | `contacting.md` | Done |
| customers | filament-customers | No | Yes | High | `customers.md` | Done |
| docs | filament-docs | Done | Yes | High | `docs.md` | Done |
| engagement | filament-engagement | No | Yes | High | `engagement.md` | Done |
| events | filament-events | No | Yes | High | `events.md` | Done |
| feedback | filament-feedback | No | Yes | High | `feedback.md` | Done |
| growth | filament-growth | Done | No | Medium | `growth.md` | Done |
| inventory | filament-inventory | Done | Yes | Medium | `inventory.md` | Done |
| jnt | filament-jnt | No | Yes | High | `jnt.md` | Done |
| orders | filament-orders | No | Yes | High | `orders.md` | Done |
| organizations | filament-organizations | Done | Yes | High | `organizations.md` | Done |
| persons | filament-persons | Done | Yes | High | `persons.md` | Done |
| pricing | filament-pricing | Done | No | High | `pricing.md` | Done |
| products | filament-products | No | Yes | High | `products.md` | Done |
| promotions | filament-promotions | Done | Yes | High | `promotions.md` | Done |
| seating | filament-seating | No | No | High | `seating.md` | Done |
| shipping | filament-shipping | Done | Yes | High | `shipping.md` | Done |
| signals | filament-signals | Done | Yes | High | `signals.md` | Done |
| tax | filament-tax | No | Yes | High | `tax.md` | Done |
| ticketing | filament-ticketing | No | Yes | High | `ticketing.md` | Done |
| vouchers | filament-vouchers | Done | Yes | High | `vouchers.md` | Done |

### Standalones

| Package | Migration | Breaking Changes | Highest Severity | Audit File | Status |
|---|---|---|---|---|
| checkout | No | Yes | High | `checkout.md` | Done |
| csuite | No | No | Medium | `csuite.md` | Done |
| membership | No | Yes | Medium | `membership.md` | Done |
| moderation | No | Yes | High | `moderation.md` | Done |
| references | No | No | High | `references.md` | Done |

## Open Migrations (0)

All 11 migration packages are closed — 7 implemented, 4 dropped/corrected with evidence. See [`migration-record.md`](migration-record.md).

## Highest-Risk Packages (Critical severity, 0)

No Critical holders remain. `cashier` cleared 2026-09-08 and `inventory` A1 was falsified to Medium and cleared with this track; both rate Critical only as history.

Dominant remaining risk themes: logged micro-dependencies owned by other tracks (see the per-audit residual notes), and durable-ledger reconciliation as an operator path rather than an automatic one.

## Major Cross-Package Architectural Issues (remaining)

1. **Owner-scoping gaps (security).** `events` is settled: 7 direct owner models, 46 relation-via-event models, and 11 intentional catalog/pivot/submission exceptions, with parity tests. Settled: `addressing`/`persons` owner morphs shipped, `growth`/`signals` parity enforced, `tax` demoted (global scope applies), `orders` intake scoped both paths, `customers` resolver scoped + model-hook uniqueness + owner-aware unique index (see `code-fixes-record.md`). See `events.md`, `tax.md`.
2. **Identity concept split (done 2026-09-08).** Topology decided and implemented: `Person` shared root, `Customer` owner-scoped + `person_id`, `Organization` tenant, `EventOrganizer` event-scoped. Native contact layer removed (Contacting-only). See `persons.md`, `customers.md`.
3. **Address lineage (done).** `addressing` canonical with resolver everywhere; `customers` pilot live and legacy storage retired 2026-09-12 (rewired to canonical primaries, table dropped); orders pilot implemented end-to-end (write path, consumers, invoice reads, composer contract); events pivot via resolver with `Venue`/`EventLocation` adopted. See `addressing.md`, `events.md`, `orders.md`.
4. **Payments modeled three times (done 2026-09-08).** `cashier` thin multiplexer, `cashier-chip` canonical billing, `chip` HTTP/API owner, durable idempotency ledger — collapse complete; see `cashier.md`, `cashier-chip.md`, `chip.md`. Checkout keeps a thin delegating mapper plus TTL/single-use/rate-limited callbacks; see `checkout.md`.
5. **Pricing/promotions/vouchers (residual).** Dead promotion strategies deleted, BOGO promotion type removed (voucher-side BOGO mechanic untouched and live), voucher provenance canonicalized, broken cross-package class references fixed, pricing bridged through promotions' public contract, validator consolidated. See `pricing.md`, `vouchers.md`.
6. **Events ↔ ticketing ↔ seating boundary (cleared 2026-09-11).** Event-side ticketing DTO/action forks remain removed; ticketing’s registry placement and transactional batch issuance, seating’s set-based allocation, explicit GA mode handling, and Filament enum alignment are settled. Read-only event dependencies and deferrals are recorded in `ticketing.md` and `seating.md`.
7. **Foundation residue.** Models moved, money strict, navigation canonical, Octane flush wired, helpers grouped, stubs unified (see `code-fixes-record.md`; dependency-direction guard in place). Remaining: `ManageCommerceNavigation` feature test; Octane soaking via production telemetry (first-deploy condition met). See `commerce-support.md`, `authz.md`, `jnt.md`.
8. **Filament adapters duplicating domain.** Snapshot dual-write (`filament-cart`), condition-application duplication, `CreateCustomer`/`UpdateCustomerProfile` parsing duplication, customer merge split across core/Filament, `GrowthStatsAggregator` N+1. See `cart.md`, `customers.md`, `growth.md`.
9. **Zero tests in nearly all packages.** Repo-root `tests/src/<Area>/` has partial coverage only. Test-absence findings are uniformly Medium per the rubric. Every audit lists highest-value first tests.
10. **Migration hygiene (residual).** Inventory decimal columns closed
  2026-09-07 (drop migration, zero readers); the duplicate historical
  `events` migration number is documented and did not require a new
  migration in this pass. Feedback 9-table split done 2026-09-08.

## Second pass (hardening) + migration track

- **Severity normalized, no inflation.** Critical is used only for breach-class security holes, corruption/data-loss risks, and broken integrity behavior (0 files currently hold Critical: `cashier` cleared 2026-09-08, inventory A1 falsified and cleared; full trail in `migration-record.md` + `code-fixes-record.md`).
- **False claims removed or corrected** (see hardening notes in prior revision; full list in `migration-record.md` deviations).
- **Migration track completed 2026-09-07** (historical snapshot; inventory implemented after — see `inventory.md`). At the time: 10 of 11 migration packages settled: 6 implemented (`pricing`, `organizations`, `addressing`, `vouchers`, `promotions`, + `growth` parity assertion), 4 dropped/corrected with evidence (`signals`, `shipping`, `persons`, `docs`). Details, evidence, commit list (24 commits after base), and deployment gates in [`migration-record.md`](migration-record.md).
- **Reviewer spot-checks (all held).** Unscoped `findExistingIntake`, zero `HasOwner` in persons, wrong voucher import fixed to the real class, serial enum-vs-morph mismatch, empty-`getPages()` phantom chip resources, enum-removal consistency (zero `PromotionType::BuyXGetY` references repo-wide), guarded checkout-listener registration, boot-time owner-parity assertion.

## Recommended Overall Refactor Order (remaining work)

1. **Deployment gates first** (see `migration-record.md`): dev-only, no backfills — delete-and-rerun accepted; promotions migration timing; PHP 8.4 CI.
2. **Cashier multiplexer (done 2026-09-08):** was webhook verify/handle, unscoped reads, 100× amount bug, table-less records, gateway truth — collapsed to thin multiplexer. `cashier-chip`/`chip` gateway legs done same day. Money-path adversary fixes verified green after.
3. **Chip crash-recovery window (closed 2026-09-08):** was gateway-success-then-crash double-charge — now durable ledger reserve/replay + keyed mutation legs; all 10 adversarial proofs green.
4. **Events isolation (done 2026-09-09):** owner parity implemented and verified; remaining exceptions and deferrals are recorded in `events.md`.
5. **Identity/address consolidation (done).** Topology live, customers pilot + native-layer removal done, resolver shipped, physical index batches applied, orders pilot closed. See `addressing.md`, `events.md`, `orders.md`.
6. **Foundation residue:** ManageNav feature test; Octane soaking via production telemetry.
7. **Pricing/vouchers residual (done).** Pricing bridged through promotions' public contract; voucher validator consolidated. See `pricing.md`, `vouchers.md`.
8. **Events/ticketing/seating + affiliate-network:** events/ticketing/seating cleared 2026-09-11; programs-vs-offers boundary is documented in `affiliate-network.md`.
9. **Remainder:** foundation residue (item 7) and per-audit residual notes only; no open tracks.
10. **Tests throughout:** add the listed first tests per package before touching risky code; keep `--parallel` per repo test guidelines.
