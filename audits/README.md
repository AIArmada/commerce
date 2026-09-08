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

| Package | Filament Adapter | Migration | Breaking Changes | Highest Severity | Audit File | Status |
|---|---|---|---|---|---|
| addressing | filament-addressing | Done | Yes | High | `addressing.md` | Open |
| affiliate-network | filament-affiliate-network | No | Yes | High | `affiliate-network.md` | Open |
| affiliates | filament-affiliates | No | Yes | High | `affiliates.md` | Open |
| authz | filament-authz | No | Yes | Medium | `authz.md` | Done |
| cart | filament-cart | No | Yes | High | `cart.md` | Open |
| cashier | filament-cashier | No | Yes | Critical | `cashier.md` | Done |
| cashier-chip | filament-cashier-chip | No | Yes | High | `cashier-chip.md` | Done |
| chip | filament-chip | No | Yes | High | `chip.md` | Done |
| commerce-support | filament-commerce-support | No | No | Low | `commerce-support.md` | Done |
| communications | filament-communications | No | Yes | High | `communications.md` | Open |
| contacting | filament-contacting | No | Yes | High | `contacting.md` | Open |
| customers | filament-customers | No | Yes | High | `customers.md` | Open |
| docs | filament-docs | Done | Yes | High | `docs.md` | Open |
| engagement | filament-engagement | No | Yes | High | `engagement.md` | Open |
| events | filament-events | No | Yes | High | `events.md` | Open |
| feedback | filament-feedback | No | Yes | High | `feedback.md` | Done |
| growth | filament-growth | Done | No | Medium | `growth.md` | Open |
| inventory | filament-inventory | Done | Yes | Medium | `inventory.md` | Open |
| jnt | filament-jnt | No | Yes | High | `jnt.md` | Open |
| orders | filament-orders | No | Yes | High | `orders.md` | Open |
| organizations | filament-organizations | Done | Yes | High | `organizations.md` | Open |
| persons | filament-persons | Done | Yes | High | `persons.md` | Open |
| pricing | filament-pricing | Done | No | High | `pricing.md` | Open |
| products | filament-products | No | Yes | High | `products.md` | Open |
| promotions | filament-promotions | Done | Yes | High | `promotions.md` | Open |
| seating | filament-seating | No | No | High | `seating.md` | Open |
| shipping | filament-shipping | Done | Yes | High | `shipping.md` | Open |
| signals | filament-signals | Done | Yes | High | `signals.md` | Open |
| tax | filament-tax | No | Yes | High | `tax.md` | Open |
| ticketing | filament-ticketing | No | Yes | High | `ticketing.md` | Open |
| vouchers | filament-vouchers | Done | Yes | High | `vouchers.md` | Open |

### Standalones

| Package | Migration | Breaking Changes | Highest Severity | Audit File | Status |
|---|---|---|---|---|
| checkout | No | Yes | High | `checkout.md` | Open |
| csuite | No | No | Medium | `csuite.md` | Open |
| membership | No | Yes | Medium | `membership.md` | Open |
| moderation | No | Yes | High | `moderation.md` | Open |
| references | No | No | High | `references.md` | Open |

## Open Migrations (0)

All 11 migration packages are closed — 7 implemented, 4 dropped/corrected with evidence. See [`migration-record.md`](migration-record.md). `inventory.md` still rates Critical, but that is the open A1 serial-vocabulary *code* fix, not schema work.

## Highest-Risk Packages (Critical severity, 1)

`inventory` — the remaining Critical holder (open A1 serial-vocabulary *code* fix, not schema work; see line 62). `cashier` cleared 2026-09-08 and rates Critical only as history.

Dominant remaining risk themes: gateway-side payments work (`cashier-chip`/`chip` — `cashier` itself is now a thin multiplexer), crash-recovery window on the chip money path (gateway documents no native key; local mechanism only), owner-scoping gaps in `events` models.

## Major Cross-Package Architectural Issues (remaining)

1. **Owner-scoping gaps (security).** Remaining: 57/64 `events` models on a bespoke scope framework instead of `HasOwner`. Settled: `addressing`/`persons` owner morphs shipped, `growth`/`signals` parity enforced, `tax` demoted (global scope applies), `orders` intake scoped both paths, `customers` resolver scoped + model-hook uniqueness + owner-aware unique index (see `code-fixes-record.md`). See `events.md`, `tax.md`.
2. **Identity concept split four ways.** `persons`, `customers`, `organizations`, and `events` each model identity/contact with no `person_id` bridge. Decide one canonical identity owner; see `persons.md`. (Persons tenancy question settled: shared-by-design.)
3. **Address lineage tripled.** `addressing` vs native columns in `customers`/`orders`; `customers` pilot adopted `HasAddresses` for new attachments (legacy `customer_addresses` frozen), `events` trait resolves the canonical pivot via the table resolver (full adoption still open), `orders` deferred. Remaining: legacy-address migration + default-address semantics; see `addressing.md`, `contacting.md`.
4. **Payments modeled three times (done 2026-09-08 except checkout).** `cashier` thin multiplexer, `cashier-chip` canonical billing, `chip` HTTP/API owner — collapse complete; see `cashier.md`, `cashier-chip.md`, `chip.md`. Remaining: `checkout` duplicates chip's status mapper/payload builder and confirms payment without amount reconciliation; crash-recovery outbox held. See `checkout.md`.
5. **Pricing/promotions/vouchers (residual).** Dead promotion strategies deleted, BOGO promotion type removed (voucher-side BOGO mechanic untouched and live), voucher provenance canonicalized, broken cross-package class references fixed. Remaining: `pricing.ApplyPromotionalAdjustment` still bypasses the promotions domain; voucher validator hardening. See `pricing.md`, `vouchers.md`.
6. **Events ↔ ticketing ↔ seating forks.** 4 ticketing DTO/action forks live in `events`; `TicketableTypeRegistry` lives in the Filament adapter while core needs it; per-pass issuance loop and per-seat allocate loop under `lockForUpdate` need set-based rewrites. See `events.md`, `ticketing.md`, `seating.md`.
7. **Foundation residue.** Models moved, money strict, navigation canonical, Octane flush wired, helpers grouped, stubs unified (see `code-fixes-record.md`; dependency-direction guard in place). Remaining: Octane exercised under real Octane, `ManageCommerceNavigation` feature test, `products` `store_money_in_cents` toggle, `jnt` float money math. See `commerce-support.md`, `authz.md`, `products.md`, `jnt.md`.
8. **Filament adapters duplicating domain.** Snapshot dual-write (`filament-cart`), condition-application duplication, `CreateCustomer`/`UpdateCustomerProfile` parsing duplication, customer merge split across core/Filament, `GrowthStatsAggregator` N+1. See `cart.md`, `customers.md`, `growth.md`.
9. **Zero tests in nearly all packages.** Repo-root `tests/src/<Area>/` has partial coverage only. Test-absence findings are uniformly Medium per the rubric. Every audit lists highest-value first tests.
10. **Migration hygiene (residual).** Duplicate `000066` migration number in `events`, inventory decimal columns (open migration above). Feedback 9-table split done 2026-09-08.

## Second pass (hardening) + migration track

- **Severity normalized, no inflation.** Critical is used only for breach-class security holes, corruption/data-loss risks, and broken integrity behavior (1 file — `inventory`, the A1 code fix; `cashier` cleared 2026-09-08: migration resolutions, four code-only Critical fixes, chip idempotency rewrite to its wiring gap, inventory A1 falsification; full trail in `migration-record.md` + `code-fixes-record.md`).
- **False claims removed or corrected** (see hardening notes in prior revision; full list in `migration-record.md` deviations).
- **Migration track completed 2026-09-07.** 10 of 11 migration packages settled: 6 implemented (`pricing`, `organizations`, `addressing`, `vouchers`, `promotions`, + `growth` parity assertion), 4 dropped/corrected with evidence (`signals`, `shipping`, `persons`, `docs`). `inventory` untouched. Details, evidence, commit list (24 commits after base), and deployment gates in [`migration-record.md`](migration-record.md).
- **Reviewer spot-checks (all held).** Unscoped `findExistingIntake`, zero `HasOwner` in persons, wrong voucher import fixed to the real class, serial enum-vs-morph mismatch, empty-`getPages()` phantom chip resources, enum-removal consistency (zero `PromotionType::BuyXGetY` references repo-wide), guarded checkout-listener registration, boot-time owner-parity assertion.

## Recommended Overall Refactor Order (remaining work)

1. **Deployment gates first** (see `migration-record.md`): dev-only, no backfills — delete-and-rerun accepted; promotions migration timing; PHP 8.4 CI.
2. **Cashier multiplexer (done 2026-09-08):** was webhook verify/handle, unscoped reads, 100× amount bug, table-less records, gateway truth — collapsed to thin multiplexer. `cashier-chip`/`chip` gateway legs done same day. Remaining payments work: `checkout` copies + crash-recovery outbox.
3. **Chip crash-recovery window:** gateway documents no native idempotency key, so gateway-success-then-crash still double-charges; needs a gateway-capability answer or durable outbox before the money path.
4. **Events isolation:** bespoke-scope migration toward `HasOwner` (compensation now implemented).
5. **Identity/address consolidation (partly done):** topology decided, customers pilot live, table-name resolver shipped (`addressing.md` split-brain resolved); remaining: orders/events addressing follow-ups (events hardcoded pivot prefixes), persons/org index batches.
6. **Foundation residue:** ManageNav feature test, real-Octane exercise, `products` toggle, `jnt` math.
7. **Pricing/vouchers residual + shipping/tax:** route pricing through promotions domain, voucher validator hardening.
8. **Events/ticketing/seating + affiliates/affiliate-network:** remove forks, move registry to core, set-based issuance, document programs-vs-offers boundary.
9. **Remainder:** `cart` snapshot consolidation, `products` config/policy fixes, `communications`, `engagement`, `feedback`, `docs`, `jnt`, standalones (`moderation` expiry sweep, `references` tenancy, `membership`, `csuite` bundle requires).
10. **Tests throughout:** add the listed first tests per package before touching risky code; keep `--parallel` per repo test guidelines.
