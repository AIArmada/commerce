# Final Repair Audit

## Overall

- Repair campaign: `audits/verified-review-2026-09-13.md` (5517 lines) → `REPAIR_PROGRESS.md` (3691 lines, 39/39 units COMPLETE, 0 remaining).
- Adversarial review verdicts: 8 CONFIRMED + fixed, 5 DOWNGRADED (no action, documented), 1 REFUTED, plus sweep-phase findings below.
- Sweep-phase additional defects found: 7 (1 morph-config, 3 duplicate indexes, 1 MySQL ESCAPE defect, 1 voucher double-escape, 1 stale rollout test, 1 rollout gap).
- Sweep-phase additional defects fixed: 7. Remaining blocked: none (1 deferred item documented: composite-leftmost index review, perf-only, no correctness impact).
- Review-loop fixes (post-audit): 5 stale-test failures (3 promotions, 2 vouchers-migration) fixed; 9 Pint violations fixed (incl. signals `mb_strlen($x, '8bit')` to preserve byte semantics against the `mb_str_functions` rule); Pint clean on all 104 uncommitted PHP files; PHPStan L6 clean on all 26 src-changed packages.
- Review→audit→fix loop converged: 3 iterations (fix round, PHPStan remainder + full-diff reads + model-arg verification + index math, coverage completion). Final iteration found zero defects. 113 paths reviewed: every src diff read, every test delta checked for weakening, all suites for touched areas green, no out-of-scope paths, no stale references. One open DECISION (not defect): `morph_key_type=int` support (document-as-unsupported vs converge-46-migrations) — see CORRECTION note above.
- Tree state: HEAD `0b6f7251d` (cart/customers migration-date collapse committed by a parallel process mid-session; verified intact, not mine). Working tree holds the full repair + sweep change set uncommitted by design (88 paths: 79 modified, 5 staged deletions, 4 untracked).

## Package Status

| Package | Result | Additional Issues | Corrections | Verification |
|---|---|---:|---:|---|
| commerce-support | FIXED (webhook NULL-hash) + SWEPT (LikeSearch ESCAPE) | 1 | 2 | 336 passed |
| cashier-chip | FIXED (null→0 renewal, expired claim) | 0 | 2 | prior session green |
| affiliates | FIXED (reconcile, clamp) + SWEPT (dup indexes, DBLookup LIKE) | 3 | 4 | 1160 passed, 5 skipped |
| ticketing | FIXED (dual-current, validator, owner bind) + SWEPT (registration morph) | 1 | 4 | 86 passed |
| events | FIXED (refund locks) + SWEPT (organizers dup index, search LIKE, revoke cast) | 3 | 4 | 286 passed |
| seating | SWEPT (holder morphs config-independent) | 1 | 2 | 97 passed |
| vouchers | FIXED (fail-closed validator) | 0 | 1 | prior session green |
| signals | FIXED (strlen) + SWEPT (SignalCondition ESCAPE) | 1 | 2 | 126 passed |
| addressing | VERIFIED (sampled) + SWEPT (SearchAddressAreas ESCAPE) | 1 | 1 | 200 passed |
| inventory | SWEPT (serial LIKE ×2) | 1 | 1 | 1161 passed, 6 skipped |
| cart | SWEPT (byName LIKE) | 1 | 1 | 1075 passed, 2 skipped |
| authz | SWEPT (SuperAdmin LIKE) | 1 | 1 | 26 passed |
| customers | VERIFIED (shim sweep: fallback is a live data guard) | 0 | 0 | — (MergeCustomersPage suite below) |
| filament-affiliates | SWEPT (4 raw LIKE sites) | 2 | 2 | 334 passed |
| filament-events | SWEPT (CheckInConsole, EventRegistrationResource ×2) | 3 | 3 | 22 passed |
| filament-cart | SWEPT (AbandonedCartsWidget) + contract-change test update | 1 | 2 | 202 passed |
| filament-customers | SWEPT (MergeCustomersPage) | 1 | 1 | 54 passed |
| filament-affiliate-network | SWEPT (marketplace LIKE) | 1 | 1 | 86 passed |
| filament-docs | SWEPT (DocForm LIKE) + rollout-test repair | 2 | 2 | 103 passed |
| filament-vouchers | SWEPT (bridge + widget ESCAPE + double-escape) | 2 | 2 | 69 passed |
| filament-seating | SWEPT (OwnerUniqueRule migration) | 1 | 1 | 13 passed |
| filament-ticketing | VERIFIED (regression) | 0 | 0 | 15 passed |
| filament-inventory | VERIFIED (regression) | 0 | 0 | 47 passed |
| docs | VERIFIED (structure incl. new morph_key_type paragraph) | 0 | 0 | 219 passed |
| all other packages | repair COMPLETE per REPAIR_PROGRESS (not re-audited line-by-line; see reconciliation) | 0 | 0 | per REPAIR_PROGRESS log |

## Workflow synthesis (adversarial, read-only — verified inline)

- Verdict: 0 CLEAN / 12 NEEDS-FIX; dedup 5H/19M/55L (each proven in current source before fixing; see log below).
- Backlog: MAP-OK claimed; FALSE-implemented items alleged in g4 (R2#7/#8/#22, R2#1), g6 (R1#17), g1 (R1#18); BLOCKED visibility OK; half-fix alleged g10#17/NEW-045; 63 NEW spot-OK, 17 search-only.
- Cross-group: LIKE without ESCAPE (g3/g10/g12), open allowlists (g3/g8/g5), float residue (g1/g4), accum/N+1 (g1/g2/g3/g8), price trust (g5/g6/g8), 23000 w/o 23505.
- Schema/shim: zero transitional by design claimed; cleanup list: g12 stubs, cust000007, InvenLevel alias, Customer fallback, MoneyHelper, dup indexes; open chip/jnt morph type MED.
- Unresolved carried: no test runs by reviewers; race/mem repro static-only; MySQL/pgsql code-read; excerpt/grep-only bodies; 17 NEW search-only incl NEW-006 missing; filament remainders.

## Backlog reconciliation (Phase 1 — complete for H/M batch + sweeps)

- Source: `audits/verified-review-2026-09-13.md` (5517 lines) vs `REPAIR_PROGRESS.md` (3691 lines) vs current source.
- Method: H/M batch verified inline (log below); repair-log terminal states sampled 8/8 present (see four-way reconciliation); sweeps closed the LIKE/morph/index/shim residue classes repo-wide.

## Verification log (inline, adversarial — H batch)

- H-g6 null→0 free renewal: CONFIRMED. `swap()`/builder allow null unit_amount (by design) → `calculateSubscriptionAmount` coerces ??0 → `executeAttempt:141` auto-renews 0 without payment. Same root cause reaches `Subscription::charge()` null-branch and `SubscriptionBuilder::checkout()`. Fix at money-movement decision points (fail closed on unknown prices); explicit-0 free renewal (pinned test) preserved. Coupon-discount paths (`applyCoupon`, `CreateChipSubscription`) compute on coerced base — include in fix.
- M-g6 expired claim: CONFIRMED as liveness defect (unique blocks all future claims; reconcile ignores expired 'claimed'; subscription stuck Active-overdue). NOT currently double-charge (no path re-executes expired claims). Fix: reconcile step fails expired claims closed (recordFailure CLAIM_EXPIRED → PastDue → webhook recovery) + warning log. Claim-level no-reclaim pin (test) preserved.
- H-g7 reconcile forceFill: CONFIRMED. `reconcilePayout` bypasses `transitionTo` (terminal→anything, incl. Completed→Failed resurrection → double-pay) and never marks conversions Paid (vs `UpdatePayoutStatus::syncConversions`); fund release in separate txn (crash window); no fallback detach. Fix: canTransitionTo guard (webhook-safe false) + transitionTo + Paid sync + in-txn release + fallback detach.
- M-g7 upline clamp: CONFIRMED, root cause in central `CommissionCaps::clamp` (0→minimum conjures money; unpinned for 0; negatives pinned, respected). Fix centrally: 0 stays 0 (minimum floors earned commissions only). Covers upline + explicit-0 + missing-commission + 0-rate paths.
- H-g8 null actor: DOWNGRADED as stated (machine transfers explicitly pinned by test 'allows machine transfers without an acting user'; no untrusted in-repo caller). Real gap is the +owner half: new holders don't inherit pass owner (ambient-context dependent). Fix: bind new-holder owner to pass owner.
- H-g8 dual-current: CONFIRMED. `resolveHolder` pre-saves new holder is_current=true; `transfer()` previous lookup (`holder()->first()`, no order) can return the NEW row → self-transfer + old stays current; default is_current=true; no uniqueness; pass-lock serializes but lookup is nondeterministic. Fix: transfer owns the switch (unsaved build → save non-current → lock+unset ALL others → set new current); deterministic previous for record; document why no partial-unique (MySQL portability; lock serializes).
- M-g8 AddCart: REFUTED. Reserved-key guard + max_participants cap already present and pinned by CartAttributesTest. No action.
- M-g8 Issuer: DOWNGRADED. afterCommit event dispatch is correct; no in-repo throwing listeners; retry-safety owned by callers (events caller recounts; OrderPaid single-dispatch). Host-listener failures are host responsibility. No action; residual noted.
- M-g8 Hold validator (HolderAttributesValidator::validateHolderModel): CONFIRMED — Model path lacks allowlist + unconditional exists parity with attributes path. Fix: converge.
- M-g8 Hold availableSeatsQuery: DOWNGRADED. Skip-locked seat selection + locking recheck is sound on MySQL/PG; naive unique(seat_id) would corrupt converted-hold history; partial unique not portable. Lock design is the uniform guarantee. No action.
- M-g8 Reg price verbatim: DOWNGRADED. All in-repo callers pass server-derived prices (ticket type / order snapshot / 0); order snapshots legitimately differ from current ticket price, so blanket ticket-verification would be WRONG. Price authority correctly lives at cart time (server-side ticket price). No action.
- M-g8 Refund double-restore: CONFIRMED (concurrent duplicate EventRegistrationRefunded → both compute remaining=N → 2N restored; sequential already guarded by SUM). Fix: serialize listener per registration (txn + lockForUpdate). Plus source-level lock helper in RegistrationService::refund/restoreFromRefundPending (duplicate-event prevention). Siblings (approve/cancel/...) have convergent/no side effects — left, documented.
- H-g1 webhook NULL hash x2: CONFIRMED. UNIQUE(name,event_id,event_type,owner_hash) with TWO nullable members (owner_hash for ownerless; event_id for id-less payloads); concurrent duplicates both claim + both pass sequential-only duplicate check → double process. isDuplicateProcessedEvent returns false for null event_id (never dedupes id-less). Fix: ownerless sentinel hash (always stamp when dedup supported) + payload-hash event_id fallback + stamped-identity duplicate check. Also fold legacy 000006 alter stub into base create (fresh-install rule) + drop legacy-upgrade test.

## Sweep phase — morph-type convergence (G14)

- Premise check: the "46 bigint owner morphs" claim was REFUTED as stated — `SupportServiceProvider` sets `Schema::defaultMorphKeyType('uuid')` by default (`COMMERCE_MORPH_KEY_TYPE`), so every `nullableMorphs('owner')` already yields uuid-backed columns whenever the provider boots (proven by schema dump: `owner_id:varchar` on sqlite). No owner migration was touched.
- CORRECTION (review loop, supersedes the earlier blanket claim): owner morphs do NOT uniformly follow the config — ~120 migration files use explicit `nullableUuidMorphs('owner')` (config-independent) while only 46 use the guideline-literal `nullableMorphs('owner')`. Newer packages drifted to explicit uuid; the multitenancy guideline still prescribes `nullableMorphs('owner')`. Consequences: (a) de-facto owner storage is always-uuid under the default config (consistent behavior); (b) `morph_key_type=int` is effectively unsupported — it would produce a mixed schema (46 bigint vs ~120 uuid). This predates the campaign. Recommendation (decision required, not done): either document `int` as unsupported or converge the 46 to explicit uuid and fix the guideline. The `03-configuration.md` paragraph added here remains accurate as worded (it scopes the config to `nullableMorphs` declarations). Related nuance: the int-config test runs prove HOLDER-morph independence only; owner writes under `int` still rely on sqlite laxity.
- Genuine defect found: non-owner "any model" holder morphs (`seatable`, `held_by`, `allocated_to`, `registration`) used `nullableMorphs`, binding holder storage to the OWNER key-type config. Under `morph_key_type=int` (or provider-absent boots), uuid holder ids — which the repo's own actions/tests use (`allocToId: uuid`, `registrationId: EventRegistration uuid`, `EventRegistrationItem::passes()` HasMany on uuid FK) — break on strict drivers. The models already declared `@property string|null` and actions already typed `string $allocToId`: schema contradicted the declared contract.
- Fix: 4 migrations converted to explicit string morphs + index (matching the in-repo `released_by` string-morph precedent in the same table family); `(string)` casts on the two untyped `getKey()` bindings (`SeatMap::scopeForHost`, `RevokePassesForRegistrationAction`); all other binding sites verified string-safe (typed `?string` params, uuid-column sources). One doc paragraph added to commerce-support `03-configuration.md` scoping `morph_key_type` to owner morphs.
- Considered and reverted: `(string)` casts in `OwnerQuery` — proven behavior-neutral in every config×driver cell (owner columns are config-typed; int-owner-on-pgsql-uuid is unwritable regardless) → reverted to keep the change surgical; file is byte-identical to HEAD.
- Regression tests: `SeatingMorphKeyRoundTripTest` (5 tests: int-like/uuid round-trips, int-keyed + uuid-keyed `forHost`) and `PassRegistrationMorphRoundTripTest` (3 tests: issuer uuid round-trip, int-like strict-string round-trip, revoke-by-uuid-registration). Load-bearing proof: old migrations + `COMMERCE_MORPH_KEY_TYPE=int` → exactly the 3 int-like assertions fail; new migrations pass under both `uuid` (default) and `int`.
- Verification: Seating 97, Ticketing 86, Events 286, FilamentSeating 13, FilamentTicketing 15 — all green. Pint + lint clean.

## Sweep phase — duplicate indexes

- Systematic per-file scan (column-level `unique()` ∩ `index()` + composite-leftmost analysis).
- Fixed (pure waste, byte-identical redundant btrees): `affiliate_attributions.cookie_value` standalone index, `affiliate_links.custom_slug` standalone index, `event_organizers.slug ->unique()->index()` chain. No `dropIndex`/name references exist for the removed named index.
- Deliberately left: ~30 composite-leftmost overlaps (standalone index on the leftmost column of a composite unique) — narrower indexes can legitimately serve queries; removal is perf tuning with regression risk and zero correctness impact. Deferred, documented here.
- Verification: Affiliates 1160 + 5 skipped, FilamentAffiliates 334, FilamentEvents 22, Events 286 — all green.

## Sweep phase — shims and legacy

- Verified clean, no changes: zero `class_alias`, zero `@deprecated`, zero legacy config keys. Remaining `.stub` files are standard publishable migration stubs, not shims. The 9 "legacy" code comments all guard LIVE data states (null normalized values, unstamped rows, hierarchy cycles, hand-edited DSL rows) — removing them would change behavior with no verified defect, so they stay. Test-only Livewire upload shim in `tests/src/Support` is test infrastructure, out of scope.

## Sweep phase — LIKE completion (prior sweep was partial)

- Sampling found 19 residual user-input LIKE sites the earlier sweep missed, in three defect shapes: (a) raw unescaped `%{$search}%` (wildcard injection: `_` matches everything); (b) hand-rolled backslash escaping without `ESCAPE` (wrong results on sqlite, the tested driver); (c) hand `ESCAPE '\'` literals that are a MySQL syntax error (`'\'` is an unterminated literal under MySQL lexical rules — every such site breaks on MySQL).
- Core fix: `LikeSearch::escapeClause()` (new, driver-aware: `ESCAPE '\\'` on mysql, `ESCAPE '\'` elsewhere) + `whereLike()` now uses it. Pinned by 4 new `toSql` emission tests (mysql/pgsql/sqlite, no server needed) + the 6 pre-existing behavioral tests.
- Converted 13 sites to `whereLike`/`orWhereLike` + `contains()`: SerialLookupService ×2, EloquentEventSearchEngine, CartSnapshotItem::byName, SuperAdminCommand, CheckInConsole, MergeCustomersPage, AffiliateMarketplacePage (also fixed missing-backslash), AffiliateForm ×2, ProgramsRelationManager, AffiliatePayoutResource, DocForm, EventRegistrationResource ×2.
- Converted 6 expression-RAW sites to `escapeClause()`: SearchAddressAreasAction (LOWER ×3 + orderBy CASE), FilamentCartBridge, VoucherCartStatsWidget, SignalCondition ×3, DatabaseAffiliateLookup (also escaped the value), AbandonedCartsWidget (also escaped the value).
- Incidental genuine bug fixed: voucher `str_replace` escaping ran `%`→`\%` BEFORE `\`→`\\`, double-escaping its own output (`100%` → `100\\%` = escaped-backslash + wildcard). `LikeSearch::escape` replaces it in both files.
- Deliberately untouched (reviewed-safe): 4 materialized-path prefix LIKEs (paths built exclusively from model keys, charset `[0-9a-f-/]`, can never contain wildcards), 2 static-literal patterns (`'items@%'`, `'%voucher%'`), enum labels.
- Behavior note: sites that used plain `like` on pgsql (case-sensitive) now use ILIKE via LikeSearch — the established present-state search semantic everywhere else. No change on sqlite/mysql (already case-insensitive).
- Contract change (surfaced, not silent): `WidgetsTest` pinned the old no-ESCAPE SQL strings via mocks — i.e., it pinned the defect. Updated to the LikeSearch call sequence, preserving intent (pgsql CAST+ILIKE; sqlite LIKE). Behavioral suites for all touched areas re-ran green (ledger below).

## Sweep phase — OwnerUniqueRule rollout completion

- Found red: `FilamentDocs/Unit/OwnerScopedUniqueTest` (3 errors) — the rollout deleted `DocsOwnerScope::scopeUniqueRuleToOwner` but never updated its test. Fixed by migrating the test helper to the replacement API (`OwnerUniqueRule::scopeToOwner($rule, Doc::class)`), mirroring production `DocForm` usage. 3/3 pass.
- Found gap: rollout migrated docs + products but left `SeatingOwnerScope::scopeUniqueRuleToOwner` (logic-identical to the shared helper for SeatMap's standard columns). Migrated `SeatMapResource` + `SeatMapOwnerScopingTest` to `OwnerUniqueRule::scopeToOwner($rule, SeatMapModel::class)` and deleted the helper file. Equivalence verified against `HasOwnerScopeConfig` semantics (identical whenever seating config loads; MORE consistent when absent).
- No other stale rollout references exist (`ProductsOwnerScope`/`LikePattern`: zero references; no other `scopeUniqueRuleToOwner` definitions).

## Verification ledger (this session, all observed)

- Seating 97 · Ticketing 86 · Events 286 · Affiliates 1160+5 skipped · CommerceSupport 336 · Addressing 200 · Authz 26 · Cart 1075+2 skipped · Signals 126 · Inventory 1161+6 skipped
- FilamentSeating 13 · FilamentTicketing 15 · FilamentAffiliates 334 · FilamentEvents 22 · FilamentCustomers 54 · FilamentAffiliateNetwork 86 · FilamentDocs 103 · FilamentVouchers 69 · FilamentInventory 47 · FilamentCart 202 · Docs 219
- Morph int-config runs: 5 + 3 passed; load-bearing proof: 3 failed on old schema as expected
- Review-loop suites: CashierChip 585 · Vouchers 925 + 7 skipped · FilamentProducts 52 · FilamentSignals 38 · FilamentAddressing 55 · FilamentPricing 62 · FilamentPromotions 46 · FilamentDocs 103 · FilamentVouchers 69 · FilamentInventory 47 · Signals 126 (all green after fixes)
- PHPStan L6 (`--debug` for sandbox TCP block): 26/26 src-changed packages, no errors. Pint: clean on all 104 uncommitted PHP files. `php -l`: clean on every touched file.
- One paratest flake encountered (worker file contention in TestCase DB setup, before any test logic); green on immediate re-run. One real mock-contract failure (`WidgetsTest`) surfaced by the LIKE conversion — fixed via the documented contract change, not a flake.

## Four-way reconciliation (workflow × verified-review × REPAIR_PROGRESS × source)

- REPAIR_PROGRESS claims 39/39 units COMPLETE, 0 remaining. The H/M adversarial batch confirmed-or-downgraded every workflow NEEDS-FIX item with pinned source evidence (8 fixed, 5 downgraded with cause, 1 refuted) — no workflow item was left unaddressed.
- Terminal-state sampling (8 diverse repair claims re-verified in current source this session): OwnerFilesystem traversal validator (present), AddressData floatOrNull `is_numeric` guard (present), CommissionCaps zero-stays-zero (present), cashier-chip `hasUnknownPrices` fail-closed at all 4 money-movement points (present), ticketing transfer current-switch (present), VoucherValidator fail-closed branches (present), OwnerUniqueRule deployed in filament forms (present), LIKE sweep (RESIDUE FOUND → completed this session, see above). 8/8 present-or-repaired.
- Honest scope: a full line-by-line re-verification of all ~1000+ repaired findings was not performed in this session; REPAIR_PROGRESS entries carry their own per-unit verification runs (all green at repair time). This audit re-proved the H/M batch, sampled across R1/R2/core/filament, and closed every residue class it found (LIKE, morph-config, dup-index, shim, rollout). No open defect is known.
- Carried residuals (unchanged from campaign): concurrent-race repros are lock-design arguments verified by code-read + sequential tests (no live race harness); MySQL/pgsql behavior verified by SQL-string + lexical-rule reasoning where no server was available (LikeSearch emission tests); full-suite runs remain sharded-CI-only (hours locally).

## Deferred / no-action items (explicit)

- Composite-leftmost index overlaps (~30): perf-only, no correctness impact; removal needs per-query analysis. Left as-is.
- `OwnerQuery` string casts: proven neutral, reverted. Owner key-type contract is enforced by config choice (`morph_key_type`), documented in `03-configuration.md`.
- pgsql `uuid`-column + integer-keyed owner remains unwritable by construction — apps with integer owners must set `morph_key_type=int`. Documented behavior, not a defect.
- Filament table-search pgsql case-sensitivity change (like→ILIKE): intended convergence, noted above.

