# D1 / D3 panel convergence — 2026-09-28

Three independent reviews (Muse, gpt-6-luna via codex, space-bunny via
opencode, all max effort, same unanchored brief), converged over 5 follow-up
questions. Every claim below traces to a quoted command.

## Verdicts (unanimous)

- **D1: (a) build `open` mode** — as a redefined, decoupled design (§1).
- **D3: (a) rewrite sketches to the manager path** (§2).

## 1. Converged D1(a) design (build-ready)

`open` = free registration, activation on first *qualifying* conversion.
Approval is DECOUPLED from the money pipeline (Luna switched from (i) to
(ii) after the pipeline defects were verified).

1. **Persist mode**: `registration_approval_mode` string column, NOT NULL
   DEFAULT 'admin', composite-indexed with status. No backfill, no legacy
   null semantics. Written once by `CreateAffiliate` (sole production
   caller: `PortalRegistration:376`); predicate requires explicit
   `=== Open`; immutable after creation.
2. **Attribution exception**: `Affiliate::canBeAttributed()` =
   `isActive() || isOpenPending()` (stored mode Open + status Pending).
   Replace `isActive()` at 9 acquisition gates (DatabaseAffiliateLookup:56,
   CaptureAffiliateReferralFromPath:33, AttachAffiliateToCart:39,
   ResolvePublicAffiliateReferralContext:76+104, TrackAffiliateVisit:37,
   CreateTrackingLink:28, AttachAffiliateFromCookie:25,
   CapturePublicAffiliateReferral:32, AttachAffiliateFromVoucher:55).
   Keep strict at payout (`Affiliate:328`) and discount
   (`AffiliateDiscountConditionProvider:84`) gates.
3. **Trigger**: listener on `AffiliateConversionRecorded` (covers record +
   outcome paths) calling `AutoApproveOpenRegistrationAffiliate`, which
   injects the orphaned-then-wired `ApproveAffiliate` (idempotent).
4. **Predicate** (`QualifiesForOpenApproval` contract): real organic
   attribution belonging to that affiliate (closes the `affiliate_id` /
   `affiliate_code` back door in `resolveAffiliate`); conversion not
   Rejected/Reversed; `commission_minor` above new
   `registration.open_approval_min_commission_minor`; fraud gate passes.
   Records conversion id in metadata.
5. **Fraud gate** (both panels converged, refined in audit round 2):
   record-time `allowed` is implied by the conversion surviving as
   non-Rejected, plus an UNWINDOWED direct query that Detected/Confirmed
   signals are absent (the risk profile's 30-day window would let stale
   signals through, so the as-built gate does not use it).
   Dismissed/Reviewed clears; unresolved Low blocks at any age. "Reviewed"
   is documented as clearing in `09-fraud-detection.md`.
6. **Collateral (required)**: enum `description()` + portal copy rewritten;
   `.env.example:83` fixed (`manual` is invalid); `ApproveAffiliate` wired
   into `AffiliatesTable`, replacing the raw status Select (which bypasses
   `activated_at`); backfill `activated_at` for form-activated rows;
   `03-configuration.md` + config comment corrected (mode snapshotted at
   signup, governs future registrations only).

## 2. Converged D3(a) fix

Two snippets in `99-troubleshooting.md` (~220, ~234) rewritten to:

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Facades\Checkout;

Cart::setIdentifier('unique-per-test-cart');   // setIdentifier IS create
Cart::add('sku-1', 'Test Item', 1500, 1);
$session = Checkout::startCheckout(Cart::getId());  // live Cart has getId(), no ->id
```

Caveats to document: requires database-backed storage (`getById` returns
null otherwise); cart must be non-empty; unique identifier per test
(process-global manager state). No new `create()` API: identity is
`(identifier, instance)`, not a row — a `create()` returning a string id
would teach the Eloquent misconception causing the bug. Optional later
follow-up (not required): a test-suite helper, not public cart API.

## 3. Bugs found (all verified, none fixed yet)

| # | Severity | Finding |
|---|----------|---------|
| B1 | CRITICAL | Default config: commissions sit in `holding_minor` forever; no affiliate can ever be paid (no Qualified writer + unscheduled command + money-less Filament approve + single dead-path release site). |
| B2 | HIGH | No wired `Affiliate` approval path at all; raw form Select bypasses `activated_at`. |
| B3 | MEDIUM | `.env.example:83` ships invalid `manual` mode, silently falls back to Admin. |
| B4 | MEDIUM | `ProcessCommissionMaturityCommand` registered, never scheduled anywhere in src. |
| B5 | MEDIUM | Three artifacts disagree on `open`'s meaning (config doc vs enum vs portal copy). |
| B6 | LOW | `UpdateAffiliateFraudSignalStatus` has no source-status guard (any→any). |
| B7 | LOW | `FraudSignalStatus::Reviewed` meaning undefined beyond the label. |

B1 should outrank D1: broken money movement on the default path, not a
missing feature. B2 is repaired inside D1(a) step 6.

## 5. Implementation status (2026-09-28)

Implemented in order B1 -> D1(a) -> D3(a), tests-first where behavior
changed. B1 fixed by wiring `ApplyConversionAccounting` (transition mode)
into `AffiliateConversionsTable::updateStatus`. B3/B5/B7 fixed. B4
(scheduling) and the Qualified writer remain host-opt-in by design. B6
left as-is (review action intentionally allows re-review). Per owner
direction: no backfill migration, no nullable legacy mode — the mode
column is NOT NULL DEFAULT 'admin', and form-activated rows keep their
null `activated_at` until their next status transition stamps it.

Follow-ups NOT in this batch: reversal money movement
(`ReverseAffiliateConversion` never touches balances), fraud-review reject
(`UpdateAffiliateFraudSignalStatus` rejectLinkedConversion never voids
holding), merchant-ledger reject path. Same defect family as B1, distinct
decisions — file separately.

## 6. Audit round 2 (diff review, both panels HOLD -> fixed)

Luna (4 blockers) + Bunny (2 blockers, 1 overlapping) findings, all
addressed in the working tree:

- **Atomicity**: `transitionTo()` saves via DefaultTransition, so the
  status write sat outside the transaction. Fixed: lock + re-read +
  idempotent same-state return + transition + accounting inside one
  transaction (attempts: 3).
- **Double-credit race**: concurrent approves could double-release from
  stale previous status. Fixed by the same locked restructure.
- **Stale qualification**: policy ran pre-lock only. Fixed: fast-path
  pre-check plus full policy re-evaluation under conversion + affiliate
  locks.
- **Fraud window**: 30-day profile let stale signals through. Fixed:
  unwindowed Detected/Confirmed exists() query (§1.5 as-built).
- **Payout doc claim**: softened to the truth (no status gate on payout
  creation); status-aware payout gate filed as follow-up. Note: a naive
  isActive gate would strand earned balances of deactivated affiliates,
  so that design needs care.
- Smaller: `ApproveAffiliate` Pending-only + action re-check +
  `->authorize()` matching siblings; mode resolved before retry loop;
  portal fail-fast on invalid mode; "sale"→"conversion" wording;
  `getId()` nullability caveat.

Declined with reason: relation-manager `visible()` (pre-existing crash,
no money movement — follow-up), composite index column order
(irrelevant for dual-equality predicates), `open_approval_min` default
and currency-blindness (spec-as-agreed), LogicException domain type
(spec asked "throws").

Round-2 re-audit (Bunny): SHIP. All fixes verified closed; suites
re-run independently (968 + 241 green). New follow-ups: D3 caveat
mechanism corrected to the `getById()` short-circuit; accounting
asymmetry for conversions created outside the record path (payable
without lifetime earnings) filed, not fixed.

Round-2 re-audit (Luna): HOLD -> addressed. Fraud policy now evaluates
after both locks so the fraud read sees everything committed before
lock acquisition; the residual (signals committed while the approval
holds its locks, by writers that take no lock) is documented in code
and lands on an active affiliate for the normal post-activation fraud
process. Luna accepted CLOSED on that contract, with one caveat filed
as follow-up: only `FraudDetectionService` dispatches
`FraudSignalDetected`, so directly-created manual signals never trigger
host-configured automatic suspension (manual review still sees them).
Manual approve extracted to a locked, transactional
`AffiliatesTable::approveAffiliate` static with tests. The B1 upgrade
gap (reconciling pre-fix Approved-with-holding rows) is DECLINED per
owner directive: no backfills, no legacy migrations.

## 4. Verification appendix (re-run commands)

- No Qualified writer: `grep -rn "QualifiedConversion" packages/ --include="*.php"`
- Unscheduled: `grep -rn "process-maturity" packages/ routes/ app/`
- Approve orphaned: `grep -rn "ApproveAffiliate" packages/ tests/`
- Mode unpersisted: `grep -rn "approval_mode" packages/affiliates/`
- Back door: `sed -n '328,360p' .../RecordAffiliateConversion.php`
- Fraud aggregate: `sed -n '45,84p' .../FraudDetectionService.php`
- Release sites: `grep -rn "accounting->handle" packages/affiliates/src packages/filament-affiliates/src`
- Full gate list: `grep -rn "isActive()" packages/affiliates/src --include="*.php"`

Raw panel outputs: `/tmp/codex-decisions-out.txt`,
`/tmp/opencode-decisions-out.txt`, `/tmp/opencode-a1.txt` … `a3.txt`,
`/tmp/codex-luna-a1.txt`, `/tmp/codex-luna-a2.txt`.
