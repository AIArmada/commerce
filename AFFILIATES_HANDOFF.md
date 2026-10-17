# Affiliates ↔ Affiliate-Network — Session Handoff

_Last updated: 2026-10-17, final takeover close-out. The agreed implementation
is complete and the scoped verdict is **SHIP**. Five executable package suites
passed; the 34 opt-in cash-posting tests were skipped. Scoped Pint, PHPStan,
and `git diff --check` passed. Live browser inspection of payout row actions
was unavailable because this session had no browser inventory; focused
Filament table tests cover the status matrix. Prior successful purchase/refund
flows were not repeated. No commit was made; the Cellmaxx host fix and
historical `PAY-DERK2YY5KR` remain untouched._

## 1. What was asked

1. Review and audit `packages/affiliates` and
   `packages/affiliate-network` in terms of how they work together.
2. Orchestrate fixes with GPT-6-Luna at max effort through the
   direct Codex CLI as the writer/code editor; Luna independently
   reviews/audits/fixes before handing back to the orchestrator,
   in a loop.
3. Then: no backward compatibility or legacy handling at all;
   schema changes by editing the owning migration in place.
4. Then: end-to-end Chrome testing using Chrome MCP with Luna as
   tester — `https://rizq.test` as affiliate-network,
   `https://cellmaxx.test` as affiliates, both dedicated dev
   sites that may be developed as needed; ensure both Filament
   adapters support all needed operations; E2E both Filament
   panels as well.

## 2. Non-negotiables (owner + repo rules)

- NO backward-compat/legacy: no shims, aliases, fallback
  reinterpretation of old rows, or legacy-repair branches.
  Schema changes edit the owning migration in place.
- Package boundaries: `affiliates` never imports
  `affiliate-network` code (string class/config names only);
  packages standalone via `suggest` + provider-presence/class_exists.
- Money = integer minor units + explicit currency. No DB FK
  constraints/cascades. No SoftDeletes. Migration edited in
  place for `last_referred_at`.
- Panel protocol: independent audit → converge with evidence →
  Luna writes → joint diff audit → scoped verification.
  Agreement is not evidence; every claim carries its command.

## 3. Headline outcome

Top integration risk found by both audits independently: with
both packages installed and `affiliate-network.checkout.enabled`
at its default `false`, engine order commissions were silently
dropped (engine suppressed its listener because the network
provider was present, but the network registered nothing).
Fixed and end-to-end proven in a real browser.

Joint verdicts:
- Main diff (W1–W11, V1–V4, C1–C7, F1–F5): Luna **SHIP**.
- H1/H2 delta: Luna **SHIP**.
- Remote-void slice (below): written by the ORCHESTRATOR during
  Luna's quota outage; **pending Luna adversarial audit** —
  do not treat as panel-approved yet.
- Chrome E2E: all storefront scenarios S1–S4 + member journey +
  both Filament sweeps passed with browser + DB evidence,
  except two browser items interrupted by quota (Rizq legs-tab
  reverse UI test; S5 browser refund flow using the new Refund
  action). Server-side equivalents are verified.

## 4. Orchestration model actually used

- Orchestrator (Muse, this session): independent audit,
  convergence, briefs, diff audits, independent verification,
  dev-site DB repair + host wiring application, remote-void
  slice (during Luna outage).
- Luna (`gpt-6-luna` via `codex exec`): independent audit,
  writer for W/C/F/H1/H2 rounds, adversarial diff audits,
  Chrome E2E tester (chrome-devtools MCP).
- Codex invocation patterns proven this session:
  - Read-only audit: `codex exec -m gpt-6-luna -c model_reasoning_effort="max" -s read-only -o OUT - < BRIEF > LOG 2>&1`
  - Audit of implemented diff: same with `medium`; verdicts at `low`.
  - Writer: `-s workspace-write` (commerce only).
  - Chrome E2E: `--approve-for-me` (required — MCP tools need
    approval; plain exec gives "approval policy is never").
    Instruct Luna to use ONLY chrome-devtools MCP tools
    (new_page/navigate_page/take_snapshot/click/fill_form/
    take_screenshot/list_console_messages), NOT cua_repl/browser
    plugin (empty inventory here).
  - Efforts supported by gpt-6-luna: none/low/medium/high/xhigh/max.
    `minimal` is REJECTED (400). Verify header: `grep -in "reasoning effort" LOG`.
  - Filesystem trap: sandboxed codex writes only reach the
    commerce workspace + /tmp. It cannot edit or run write-mode
    artisan inside ~/Herd/cellmaxx or ~/Herd/rizq. Luna outputs
    exact diffs ("HOST FIXES TO APPLY") which the orchestrator
    applies with edit_file. Browser actions + read-only
    sqlite3 (`mode=ro`) against site DBs work fine.
  - `--dangerously-bypass-approvals-and-sandbox` + escalated
    outer run was DENIED (approval prompts disabled). Do not retry.
- Conventions: every Pest run `--parallel`; PHPStan level 6 per
  changed `src` (Luna needed `--debug` in its sandbox for an
  EPERM result-cache issue; orchestrator runs work without it);
  Pint on changed files only; pipe long output `2>&1 | tee`.

## 5. Decision log (source commits / pending change)

- Base commit (HEAD, nothing committed): `de876bbd7` (`wip`).
- Tree state: 66 tracked files changed (+2,510/-355 per
  `git diff --stat`) + 7 untracked files (listed in §8).
- Panel trail lives OUTSIDE the repo in `/tmp/panel-affiliates/`
  (briefs, Luna reports/audits, convergence record, E2E
  screenshots). Key files:
  `orchestrator-audit.md`, `luna-audit.txt`, `convergence.md`,
  `diff-audit-round1.md`, `luna-e2e.txt`, `e2e-shots/`.
  /tmp is ephemeral — copy anything precious into the repo if
  the machine reboots.
- Related base history mentioned in briefs (prior hardening,
  treated as unverified at audit time): `bd6f3696f`,
  `9550126d2`, `b4384f899`, `4c0553a63`, `5d274ce97`.

## 6. Owner decisions (human items, resolved)

- **H1 — remote one-winner: NETWORK DECIDES.** Postbacks carry
  `network_clicked_at` (required) + `engine_touch_at` (present,
  nullable). Newer touch wins; ties → engine (mirrors local
  `>=`); null engine touch → network (`engine_unattributed`).
  Engine win → leg `superseded` (counted, never payable).
  Response/SDK return `decision: {winner, reason}`; duplicates
  never flip. Host must void/skip its engine conversion on
  `winner: network`. Reported touches are merchant-asserted
  (same trust basis as revenue), future >5 min rejected.
- **H2 — user-only identities: REQUIRE Affiliate profile** when
  the engine identity adapter is bound AND either owner flag
  (`affiliate-network.owner.enabled` OR `affiliates.owner.enabled`,
  widened in fix round 3) is on. `AffiliateProfileRequired`
  thrown at write entry points; checkout listener abstains;
  postback maps to 422. Standalone (UserKey) and owner-off
  combined installs unchanged (verified email bridge intact).

## 7. What was implemented (by round)

### Main rounds (Luna writer, SHIP)
- W1 Listener ownership: engine self-registers unless network
  checkout coordination truly registers; network honors engine
  feature flag + Cart binding; registration-matrix tests.
- W2 Idempotent `NetworkBooks` posting + conversion counters
  only on leg creation (redelivery test corrected 2→1).
- W3 Atomic `MerchantLedgerService` posting (transaction,
  unique-violation-safe, after-commit event, none on
  fraud-rejected).
- W4 Engine fulfillment validates merchant result (status,
  amount, currency); nullable receipt — failures no longer
  fabricate success metadata/receipts.
- W5 Standalone host-manual fulfillment is explicit-only
  (`fulfillsAutomatically()` seam); manual fulfill requires
  Posted leg + non-empty reference; idempotent receipt-first.
- W6 Finalizer owner-safe leg lookup + creator-owner finalization.
- W7 Postback site lookup in explicit global window (owner-mode safe).
- W8 `last_referred_at` column (migration edited in place);
  winner reads ONLY it (null = abstain); stamped on visit +
  cart-attach creation/referral paths.
- W9 Merchant→network reversal: new
  `Affiliates\Events\AffiliateConversionReversed` after-commit
  on first reversal/void; network listener
  `ReverseNetworkConversion` (marketplace-gated, owner-safe,
  loop-safe).
- W10 `NetworkConversionRecorded` re-emitted on confirmation
  (confirmed-leg notifications work locally).
- W11 Durable signed checkout snapshot
  (`network_attribution` cart→order) for outbox replay;
  smallest change in `packages/checkout` CopyMetadata step;
  signature verified in provisional listener; future
  `clicked_at` rejected (5-min skew) on cookie AND snapshot
  paths, including window<=0.
- V1 Email identity: verified-only, owner-preferring,
  deterministic (`is_verified` contact column).
- V2 Currency mismatch: counters exclude, leg commission = 0.
- V3 Reconciliation counts only active merchant postings.
- V4 Drift: `conversion_legs` tables-map, CONTEXT config keys,
  resolver docblock, postback client connectTimeout, limited
  postback response payload.
- Corrections C1–C7 + audit fixes F1–F5 as in
  `/tmp/panel-affiliates/convergence.md` (incl. removal of the
  "legacy damage" repair branch in `NetworkBooks::reverse`).

### Orchestrator-written slice (PENDING Luna audit)
Remote void symmetry (found missing by E2E S5):
- `packages/affiliate-network/src/Http/Controllers/VoidNetworkConversionController.php`
  + `POST /conversions/void` route. Same site-token auth.
  Posted → companion reversal under creator-owner context;
  Reversed/Superseded → idempotent `duplicate: true` no-op;
  Provisional → 422; unknown ref/link → 404; inactive links
  still voidable. Response `{ok, duplicate, leg_status, conversion}`.
- `NetworkPostbackClient::voidConversion(link, ref, ?reason)`
  returning `{ok, status, body, leg_status, error}`.
- Tests: `VoidNetworkConversionTest` (4) + 2 MerchantSdk tests.
- Docs: network 10-merchant-postbacks (Void endpoint),
  affiliates 15-network-merchant (refund pattern),
  docs/affiliates.md (remote symmetry sentence).
- Verified by orchestrator: AffiliateNetwork 384 passed;
  MerchantSdk 7, MerchantSeam 13 passed; PHPStan L6 clean;
  Pint PASS. Live curl smoke vs rizq + tinker host-service
  smoke (see §10). NOT yet Luna-audited.

## 8. Pending-change inventory (verify before commit)

`git status --short` at handoff: 66 modified tracked + 7 new:

New (untracked) files:
- `packages/affiliate-network/src/Adapters/Affiliates/ReverseNetworkConversion.php`
- `packages/affiliate-network/src/Exceptions/AffiliateProfileRequired.php`
- `packages/affiliate-network/src/Http/Controllers/VoidNetworkConversionController.php` (orchestrator)
- `packages/affiliate-network/src/Support/AffiliateProfileGuard.php`
- `packages/affiliates/src/Events/AffiliateConversionReversed.php`
- `tests/src/AffiliateNetwork/Feature/AffiliateProfileRequiredTest.php`
- `tests/src/AffiliateNetwork/Feature/VoidNetworkConversionTest.php` (orchestrator)

Modified areas (66 files): both providers; contracts
(Fulfillment nullable receipt + fulfillsAutomatically,
MerchantLedger/NetworkLedger active-status docs);
RecordNetworkConversion, NetworkBooks, OfferLinkService,
HostManualFulfillment, EnginePayoutFulfillment,
AffiliatesIdentityReader, provisional/finalizer/notify
listeners, TrackNetworkLinkCookie, postback controller,
reconciliation, catalog resolver docblock; engine listeners
(RecordCommissionForOrder touchOf), TrackAffiliateVisit,
AttachAffiliateToCart, Reverse/Void conversion actions,
MerchantLedgerService, MerchantIdentityService, postback
client + capture middleware, AffiliateAttribution model +
attributions migration, network legs migration + config; both
configs + package docs + CONTEXT + routing doc + checkout
CopyMetadata/doc; tests across AffiliateNetwork/Affiliates/Checkout.

Pre-existing-test corrections (agreed contract changes — do not
"restore"): NetworkLedgerBridgeTest conversions 2→1 (double-count
was the bug); NetworkJoin/CreatorBalances currency-mismatch
expectations → 0; MerchantPortal verified-email fixture;
FulfillmentTest receipts nullable (+2 pre-existing cash-posting
quarantine failures — see §11).

## 9. Dev sites state (outside this repo)

- `~/Herd/rizq` (https://rizq.test): standalone network
  (affiliate-network, filament-affiliate-network, links,
  contacting, commerce-support — path symlinks to this repo).
  Admin `admin@rizq.test` / `password`; creator
  `creator@rizq.test`; E2E-created user
  `e2e-creator-20261017@rizq.test` / `DemoAffiliates-2026!`.
  Site `cellmaxx.test` verified, postbacks enabled. Offer
  "CellMaxx 5G Signal Booster Pro" 1500bp / fee 200bp,
  approval OFF (Luna toggled during tests, restored).
  Links: seeded `KdAdhHTF7G` (2 clicks, 2 conversions),
  creator `MPYZN3S4Gn` (1 click, 0 conversions).
- `~/Herd/cellmaxx` (https://cellmaxx.test): engine merchant
  (affiliates + filament-affiliates + cart/checkout etc.,
  symlinked). Admin `admin@cellmaxx.test` / `password`.
  DB was `migrate:fresh --seed` by orchestrator (stale schema:
  missing `last_referred_at`, `purpose`). Backups:
  `database/database.sqlite.bak-e2e-20261017` in BOTH apps.
  Host files edited by orchestrator (Luna-authored diffs):
  `app/Http/Controllers/StoreController.php`,
  `app/Services/OrderPostbackService.php` (+ `void()` added by
  orchestrator), `app/Filament/Resources/CellmaxxOrders/CellmaxxOrderResource.php`
  (force-retry + new Refund action), `app/Policies/CellmaxxOrderPolicy.php`
  (`refund()` ability). House RIZQNET cart-attach REMOVED —
  network-only orders now record no local conversion by design.
- Tokens: cellmaxx `.env` `AFFILIATE_NETWORK_TOKEN` matches
  rizq site catalog token (Luna rotated then restored).

## 10. E2E evidence (browser + DB, independently corroborated)

- S3(a) plain: PASS. Member journey + approval + link: PASS.
- S1 network-win: order `CMX-20261017-1NUIE1EXWW`, postback
  decision network; rizq leg posted 13485/270/13215
  (15% of 89900, 200bp fee); NO cellmaxx conversion. PASS.
- S2 engine-win: order `CMX-20261017-KJB6HSR5BH`, decision
  engine; engine conversion 13485 (later Filament-reversed,
  companion -13485); rizq leg superseded. PASS.
- S3(b) engine-only: `CMX-20261017-ZOGWOHGGIA`, conversion
  approved 13485, no leg. PASS.
- S4 redelivery: duplicate=true, decision unchanged, link
  counter stayed 2 (S1+S2). PASS.
- Filament both panels: dashboard/sites/offers/applications/
  links/categories (rizq), dashboard/affiliates/conversions/
  programs/fraud/payouts (cellmaxx) all load, no JS errors;
  operations exercised incl. token rotate (restored), fee edit
  (restored), affiliate create (`E2E2026`), conversion reverse,
  manual payout `PAY-DERK2YY5KR` completed 13485. PASS.
- Remote void (orchestrator live smoke): curl report →
  posted/network; void → reversed; void again → duplicate;
  DB: original reversed + companion -13215. Host service via
  tinker on synthetic `E2E-HOSTVOID-1`: deliver/void/void all
  ok with same DB outcome. PASS (server-side).
- Screenshots: 32 in `/tmp/panel-affiliates/e2e-shots/`.

## 11. Verification status (exact commands + results)

Orchestrator re-verified the WHOLE tree 2026-10-17 15:37, after
the void slice landed (driver: `/tmp/panel-affiliates/sweep.sh`):
```
./vendor/bin/pest --parallel tests/src/AffiliateNetwork  # 384 passed (1074 assertions)
./vendor/bin/pest --parallel tests/src/Affiliates        # 1091 passed (2938 assertions)
./vendor/bin/pest --parallel tests/src/Checkout          # 266 passed (973 assertions)
./vendor/bin/pest --parallel tests/src/FilamentAffiliateNetwork # 63 passed (187 assertions)
./vendor/bin/pest --parallel tests/src/FilamentAffiliates       # 265 passed (888 assertions)
./vendor/bin/pest --parallel tests/src/AffiliateCashPosting     # 34 skipped (opt-in)
./vendor/bin/phpstan analyse packages/affiliates/src packages/affiliate-network/src packages/checkout/src/Steps/CreateOrderStep.php --level=6  # [OK] No errors
./vendor/bin/pint --test <57 changed/new php files>  # PASS
git diff --check  # 0 lines
```
TOTAL: 2069 passed, 34 skipped, 0 failed (sweep exit 0).

This is the PRE-AUDIT BASELINE. It proves the tree is green
as-is; it does NOT mean the void slice is approved. That slice
is still draft pending Luna (§12.1a). Do not read green as
shipped.

Count drift vs the earlier pre-void-client run: `Affiliates`
was 1089 then, 1091 now (+2 void-related Merchant SDK tests).
Every other count matches exactly, and nothing was edited
between the two runs.

Cash-posting suite: 34 skipped via `CASH_POST_ENABLED=1` opt-in
guard in CashPostingTestCase (pre-existing; its contents are
MySQL/PG-only and do not run on SQLite). Two cash-posting race
tests were already marked quarantined before this work (Luna
noted; unverified beyond suite skip state) — do not treat as
regressions from this change; re-check if touching cash posting.

Evidence for the 15:37 run (on disk, NOT in the repo — /tmp is
ephemeral):
- `/tmp/panel-affiliates/sweep.sh` — sweep driver
- `/tmp/panel-affiliates/sweep/pest-<Suite>.log` — one log per suite
- `/tmp/panel-affiliates/phpstan.log`, `/tmp/panel-affiliates/pint.log`
- `/tmp/panel-affiliates/changed-php.txt` — the 57 files pint covered

Still TO RUN after any Luna audit fixes: re-run the six suites
+ phpstan + pint on touched files (repo rule: never repo-wide).

## 12. Remaining work at Muse handoff (superseded by §15)

1. **Wait for Luna quota (returns 16:49 local 2026-10-17).**
   Then, in one Luna run (max, --approve-for-me):
   a. Adversarial audit of the orchestrator void slice
      (controller, route, client, tests, docs, cellmaxx
      Refund wiring) — SHIP/NO-SHIP + fixes via Luna if needed.
   b. Browser close-out: Rizq Filament Legs-tab reverse of
      S1 leg (companion + creator balance drop on screen);
      S5 refund via Cellmaxx Store Orders → Refund action
      (new paid network order first, then refund; assert order
      refunded, engine conversion voided if any, rizq leg
      reversed, postback.void recorded).
   c. Payout-balance nuance check: after completed payout
      PAY-DERK2YY5KR (13485, conversion_count=0), balance row
      reads available=13485/lifetime=13485/holding=0. Orchestrator
      could not fully reconcile available-after-payout from DB
      alone (S2 reversed + S3b approved sums) — Luna to confirm
      on-screen balances vs payout operations, or flag defect.
   d. Final Luna SHIP verdict on the whole tree.
2. Baseline is ALREADY GREEN — re-run only if Luna edits. The
   15:37 orchestrator sweep (§11) covers the pre-audit tree, so
   this step is satisfied for the current state. Any Luna audit
   fix re-opens it: re-run the six suites + phpstan + pint on
   touched files, and confirm `git diff --check` clean.
3. Owner review + commit (NOTHING is committed; do not commit
   without owner instruction. Suggested single commit or split
   by package per owner preference; include the 7 new files).
4. Optional follow-ups (not blockers): site DB backups cleanup
   (`*.bak-e2e-20261017` in both apps); copy `/tmp/panel-affiliates`
   evidence somewhere durable — OWNER DECISION 2026-10-17: panel
   trail stays in `/tmp` for now, deliberately NOT copied into the
   repo, so treat it as expendable; demo harness
   (`demo/tests/chrome/affiliate-surfaces.mjs`) untouched —
   align it with the new postback contract only if demo is revived.

## 13. Risks / unverified / watch-outs

- Luna has NOT audited: void endpoint/client, cellmaxx Refund
  wiring. Treat as draft until (1a) completes.
- Merchant-asserted touch times (H1) are trust-based by owner
  decision; a merchant can backdate `engine_touch_at` to steal
  wins. Documented in 10-merchant-postbacks.md; accepted risk.
- Session-captured `network_clicked_at` equals merchant-landing
  time, not the network-domain click instant — deliberate
  (server-observed); redirect latency shifts last-touch by
  seconds in edge races.
- `VoidNetworkConversionController` duplicates site-auth code
  from the report controller (mirrored deliberately); if Luna
  prefers extraction, let the audit decide — do not refactor
  unilaterally before its audit.
- Void response returns full leg `toArray()` consistent with
  report endpoint (pre-existing shape, kept).
- Cellmaxx checkout records `origin: 'network'` + link CODE as
  source_ref on engine conversions when a network code is
  present (host vocabulary, distinct from package
  `marketplace`/link-ID) — intentional dev-site behavior.
- Engine-owner-only config (`affiliates.owner` on, network
  owner off) now gates writes by design (H2 widening); reads
  via `findAccessible` still reject user-only actors there —
  documented, not "fixed" beyond the write gate.
- Herd serves both sites from this working tree via symlinks:
  any `git stash`/checkout in commerce instantly changes live
  dev-site behavior. Careful with worktree experiments.
- E2E data left in dev DBs (orders/conversions/legs/users
  listed in Luna reports) is test residue on dedicated sites;
  fresh-seed either app before clean-room re-runs.

## 14. Resume commands

```bash
cd /Users/saiffil/Herd/commerce
# Luna close-out (after 16:49): reuse /tmp/panel-affiliates patterns.
# Read first: /tmp/panel-affiliates/convergence.md (decision log),
# luna-e2e.txt + luna-e2e-retest.log tail (retest died on quota
# AFTER S4; its final report file luna-e2e-retest.txt was never
# written — results above come from screenshots + DB checks).
git status --short
./vendor/bin/pest --parallel tests/src/AffiliateNetwork tests/src/Affiliates tests/src/Checkout
sqlite3 "file:$HOME/Herd/rizq/database/database.sqlite?mode=ro" \
  "SELECT external_reference,status,payout_minor FROM affiliate_network_conversion_legs ORDER BY created_at;"
sqlite3 "file:$HOME/Herd/cellmaxx/database/database.sqlite?mode=ro" \
  "SELECT number,status,network_link_code,affiliate_code FROM cellmaxx_orders ORDER BY created_at;"
```

## 15. Codex takeover — current state

The original takeover used GPT-6-Luna through the direct Codex CLI for its
writer/tester workflow; the earlier CLI headers confirmed
`model: gpt-6-luna` and `reasoning effort: max`. The remote-void slice written
during the earlier quota outage is documented in §7. This close-out's narrow
reversed-post guard, payout action visibility, regressions, and docs were
implemented in the current Codex session. The starting observed HEAD was
`c5717bc17`; the original §5 base is historical. At takeover entry there were
73 modified tracked files and 8 untracked files, including this handoff. No
commit, stash, reset, or database recreation was performed.

### Completed audit and corrections

- Luna audited the remote void endpoint/client and host Refund wiring.
  Site/token/link boundaries and reversal idempotency were verified.
- A stale/concurrent void could incorrectly return `duplicate: false`.
  The response now derives that flag from the original leg under a row lock
  in the creator's owner context. Regression coverage includes HTTP site
  isolation, a different request owner, provisional refusal, inactive links,
  and a stale initial read.
- **Payout blocker found and fixed:** Filament's create page previously
  inserted an arbitrary payout row with zero linked conversions and no
  reservation. Completing `PAY-DERK2YY5KR` therefore left RM134.85 payable.
  Creation now selects approved, unlinked conversions and calls core
  `CreatePayout`, which derives accounting fields and reserves/claims them.
  Server checks reject wrong-affiliate/owner IDs; forged accounting fields
  are ignored. Existing raw payout rows remain untouched, with no backfill.
- The balance audit also double-subtracted canonical completed payouts.
  It now accounts for paid conversions linked to completed payouts and for
  direct-paid reversal clawbacks. Tests cover completion, failure/release,
  direct mark-paid, and both paid-reversal paths. Existing docs were updated.
- **Host refund race fixed:** Cellmaxx now records `refunded` before calling
  remote void. The regression reproduced the old ordering allowing normal
  and forced delivery in the lock-release gap; both are refused after the
  fix. Failed voids remain retryable.
- The owner explicitly approved installing Boost before the host edit.
  Cellmaxx now has `laravel/boost` v2.10.3 and generated guidelines.
  Its PHPUnit 12 runner needed an isolated compatible ParaTest runtime in
  `/tmp/panel-affiliates/host-test-runtime`; no extra test dependency was
  added to the app. Two outdated test fixtures were corrected.

### Browser proof completed during takeover

- Rizq Offer → Legs reversed S1 `CMX-20261017-1NUIE1EXWW` through the UI.
  Original and one negative companion were verified in the DB; creator
  earnings visibly dropped from RM132.15 to RM0.
- Fresh network order `CMX-20261017-I3CBALVKIT` was purchased and refunded
  through Cellmaxx Store Orders. Order `refunded`, `postback.void.ok=true`,
  HTTP 200, one network reversal companion, and no local engine conversion
  were corroborated. These operations were simulated, with no real payment.
- Luna's final retest later verified payout `PO-CVOHBJEWMD` completed with one
  paid conversion and zero available balance, and a fresh simulated refund for
  `CMX-20261017-YKIOD71UHX` returned HTTP 200 with one reversal companion and
  no local engine conversion. Evidence is summarized in
  `/tmp/panel-affiliates/luna-takeover-final-verdict.txt`; no purchase/refund
  flow was repeated during this close-out.

### Earlier automated verification (before final close-out)

Independent package sweep, all Pest commands with `--parallel` and teed logs:

| Suite | Result | Assertions |
| --- | --- | --- |
| AffiliateNetwork | 389 passed | 1100 |
| Affiliates | 1096 passed | 2968 |
| Checkout | 266 passed | 973 |
| FilamentAffiliateNetwork | 63 passed | 187 |
| FilamentAffiliates | 264 passed | 931 |
| AffiliateCashPosting | 34 skipped (opt-in) | 0 |

Total **2078 passed, 34 skipped, 0 failed**. The network suite was repeated
after the final void-test edit and again passed 389. Host
`OrderOperationsTest` separately passed **10 tests / 78 assertions** using
ParaTest 7.20.0, PHPUnit 12.5.35, two processes, and SQLite `:memory:`.
The old host ordering was first reproduced failing.

PHPStan level 6 passed for all `affiliates`, `affiliate-network`, and
`filament-affiliates` source plus Checkout's `CreateOrderStep.php`.
`--debug` avoided the sandbox's local TCP worker restriction. Pint passed
all 62 changed/new PHP files; `git diff --check` is clean.

Evidence remains in `/tmp` per the owner's earlier decision:

- `takeover-verification-summary.txt` — exact scope/results/log references
- `takeover-sweep/`, `takeover-phpstan-final.log`, `takeover-pint.log`
- `luna-takeover-audit-full.md` — recovered complete initial Luna audit
- `luna-takeover-host-verified.txt` — host regression and correction report
- `cellmaxx-refund-ordering-red.log`, `cellmaxx-refund-compatible-runner-fixed.log`
- `takeover-implementation-state.md` — factual routing after writer quota exit
- `e2e-shots/takeover-*` — browser evidence

### Pending at the start of this close-out (superseded below)

At handoff time, the final browser retests and independent Luna verdict were
still pending. They later completed as recorded above and in the concise
`luna-takeover-final-verdict.txt` report. The earlier writer's quota-limited
output remains historical; no commit was made or requested.

### Final close-out verification (Codex, 2026-10-17)

Focused regressions ran first, both with `--parallel` and output piped to
`/tmp/panel-affiliates`:

- `NetworkBooksTest`: 19 passed, 74 assertions. The rejected new reversed leg
  leaves no network/merchant ledger rows, counters, or conversion event; an
  idempotent replay still returns the existing leg.
- `AffiliatePayoutsTableTest`: 3 passed, 37 assertions. Pending and processing
  actions match allowed transitions; Completed, Failed, and Cancelled rows
  hide actions with no permitted transition.

Six requested package suites, all run with `./vendor/bin/pest --parallel`
and `2>&1 | tee`:

| Suite | Result | Assertions | Evidence |
| --- | ---: | ---: | --- |
| AffiliateNetwork | 391 passed | 1,114 | `codex-closeout-AffiliateNetwork.log` |
| Affiliates | 1,096 passed | 2,968 | `codex-closeout-Affiliates.log` |
| Checkout | 266 passed | 973 | `codex-closeout-Checkout.log` |
| FilamentAffiliateNetwork | 63 passed | 187 | `codex-closeout-FilamentAffiliateNetwork.log` |
| FilamentAffiliates | 265 passed | 961 | `codex-closeout-FilamentAffiliates.log` |
| AffiliateCashPosting | 34 skipped (opt-in) | 0 | `codex-closeout-AffiliateCashPosting.log` |

Total: **2,081 passed, 34 skipped, 0 failed, 6,203 assertions**. The skipped
cash-posting tests did not execute.

Static checks passed:

- `./vendor/bin/pint --test` on the four changed PHP source/test files —
  `/tmp/panel-affiliates/codex-closeout-pint.log`.
- PHPStan level 6 on `packages/affiliates/src`,
  `packages/affiliate-network/src`, `packages/filament-affiliates/src`, and
  `packages/checkout/src/Steps/CreateOrderStep.php` — no errors;
  `/tmp/panel-affiliates/codex-closeout-phpstan.log`.
- `git diff --check` — clean;
  `/tmp/panel-affiliates/codex-closeout-diff-check.log`.

The installed `spatie/laravel-model-states` is 2.14.2; its `canTransitionTo()`
API accepts the target state class. The existing browser inventory was empty,
so this run could not inspect live Cellmaxx payout row actions. The Filament
component test verifies those visibility states. The prior Luna purchase/refund
proof and the previously verified Cellmaxx 10-test/78-assertion host suite were
not repeated or changed.

### Final verdict and accepted limits

**SHIP** for the agreed takeover scope. `NetworkBooks::post()` now returns an
existing idempotent leg before status validation and rejects a new
`LegStatus::Reversed` leg before profile checks, inserts, counter changes,
events, or fulfillment. `NetworkBooks::reverse()` remains the reversal creator.
`AffiliatePayoutsTable` derives all three action visibilities from
`status->canTransitionTo(TargetState::class)`.

No companionless reversed rows were queried, repaired, or backfilled. The
already-Reversed shortcut in the remote void controller still assumes its
companion exists; whether any historical row lacks one remains unknown. This
legacy-data question is outside the agreed no-repair scope. The historical
completed payout `PAY-DERK2YY5KR` remains untouched by owner decision. No
dependency, host, or database changes were made, and no commit was created.
