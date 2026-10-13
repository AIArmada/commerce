# Share / DawahShare / Affiliates / Signals / Links — Architecture Review + SWOT

- **Date:** 2026-10-06
- **Scope:** ilmu360 share feature (`ShareTracking*`, `DawahShare*`, `DawahImpact*`, `AffiliateSignalsBridge`)
  vs commerce packages `affiliates`, `signals`, `links` (+ `affiliate-network`, `engagement` as pointers).
- **Pinned to:** ilmu360 `ef06973c`, commerce `da0ed0c6a` (ilmu360 vendors commerce via path symlinks).
- **Method:** dual independent research — Muse deep-read + `gpt-6-luna` (max effort, via Codex CLI,
  read-only) — then claim-by-claim verification against source (plus one live Pest run) and a
  recorded agreement/disagreement pass. Findings both researchers verified are marked **(2/2)**;
  single-source-but-verified findings are marked **(1/2)**. Inferences are labeled as such.
- **Mid-review velocity warning:** ilmu360 `1851cba1` (Oct 6 06:29 +0800) fixed the
  prohibited-inputs violation in the share adapter *during* this review. Anything below that
  contradicts current HEAD should be re-checked, not trusted.

## Verdict (both researchers converge: harden and keep)

Keep `affiliates` as the share attribution/outcome ledger. Do **not** extract app-owned share tables:
the ledger works (66 green share tests), the fraud engine is genuinely reused, and extraction would
reimplement attribution/expiry/idempotency semantics plus a data migration to fix what is mostly
semantic impurity. Instead: fix 5 sharp defects in place (raw IPs, missing eligibility checks,
live over-broad cleanup, retry race, landing dedupe), stop the two write-only legs
(affiliate→signals telemetry nobody reads; links rows nobody follows), and de-brand the contract.
Reconsider extraction only if share and commercial-affiliate lifecycles concretely collide.

## 1. Concept map — what each thing is vs what it should be

| Concept | True responsibility today | What it should own |
|---|---|---|
| `affiliates` pkg | Referral/commission engine **reused as** share store: zero-rate affiliate per user/guest, `?share=` attributions, visit/outbound touchpoints, Dawah outcomes as approved conversions **(2/2)** | Keep as the **attribution/outcome ledger** for share *and* commerce, with explicit share-vs-commercial partitioning (see §7). Fraud, idempotency, accounting stay here. |
| `signals` pkg | Behavioral analytics: browser/mobile telemetry (used), per-owner properties, reports; affiliate-integration leg present but dormant in ilmu360 **(2/2)** | The analytics sink. Ingest each share/affiliate fact **once**, with stable keys. Must not become a second attribution DB. |
| `links` pkg | Generic short-link engine (`/go/{slug}`, clicks, expiry). Forced transitive dep of `CreateTrackingLink`; every share link writes a `Link` row + `tracking_url` that ilmu360 never reads **(2/2)** | Nothing in the share flow as built. Either make it optional in `affiliates` or deliberately route share URLs through `/go`. Keep for affiliate-network's real offer redirects. |
| `ShareTracking*` (app) | ~3,400-line adapter+analytics+URL+cookie engine: profiles, tokens, attribution, outcomes, bridge writes, analytics reads **(2/2)** | Thin anti-corruption adapter + URL mechanics + analytics reads. Reads are already centralized in `ShareTrackingAnalyticsService` — keep that, extend the seam to cover reads. |
| `DawahShare/*Impact` (brand) | Product routes/UI/copy (`/kongsi/*`, `dawah-share.*`, panel, dashboards) **plus** leaked mechanism (TTL/cookie config, outcome enum in contract) **(2/2)** | Presentation layer only: routes, views, copy, and the mapping of product actions → neutral share outcomes. |
| `engagement` pkg | Has `Share` model (sharer/subject/channel/token/lifecycle) — outbound action records, **no** attribution/cookie/visit/outcome semantics **(2/2)** | Pointer only: candidate home for *outbound share actions* later, not a replacement for attribution. Adopting it now would add a boundary, not remove one. |

## 2. How the flow works today (verified)

1. **Payload:** `dawah-share-panel` → `GET /kongsi/payload` (or API `/share/payload`) → `sharePayload()`.
   Authenticated users get/create a zero-rate `Affiliate` (`MI…`); guests get a global
   `Anonymous Share Profile` (`MG…`, keyed by signals visitor cookie → session → IP+UA hash).
2. **Link:** create-or-reuse `AffiliateLink` per (affiliate, destination, origin). Creation goes through
   `CreateTrackingLink` (which **also writes a links-package `Link` row**), then the app assigns the
   16-char share token to `custom_slug` post-create. Shared URL = `destination?share=<token>`.
3. **Outbound:** provider redirect or copy/native-share POST → `outbound_share` touchpoint +
   `AffiliateLink.clicks++`. Note: `clicks` here means *outbound share attempts*, not recipient clicks.
4. **Landing:** global web middleware `TrackDawahShareAttribution` → `captureRequest()`: `?share=` token
   → upsert `landing` attribution (cookie), unconditional `landing` visit touchpoint, bridge call.
   Cookie-only revisits refresh attribution and write `return`/`navigated` touchpoints (5-min dedupe).
5. **Outcome:** product actions (signup, event going/save/registration/checkin/submission, follows,
   saved search) → `recordOutcome()` → approved `AffiliateConversion` via `RecordAffiliateOutcome`
   (`dispatch_event=false`), counter++, bridge call.
6. **Signals:** `AffiliateSignalsBridge` writes `affiliate.attributed` / `affiliate.conversion.recorded`
   (trusted) into the single global `ilmu360` property. **Nothing reads them** (verified by exhaustive grep).
7. **Analytics:** `DawahImpact` pages, `ShareAnalyticsController`, Filament `ShareAnalytics` all read
   affiliate tables through `ShareTrackingAnalyticsService` → affiliates-backed analytics services.

## 3. Ranked defects (fix in place, in this order) — agreed 2/2 unless noted

1. **Raw IPs violate the storage contract.** Migrations state `ip_address` holds a SHA-256
   pseudonymized hash, never a raw address; the app writes `$request->ip()` raw in all three paths
   (attribution, visit touchpoint, outbound touchpoint). Fix: use `IpHasher::hash()` (the package's
   own normalizer) at all three sites; decide retention/backfill for existing rows.
   `AffiliatesShareTrackingService.php:782,850,913` vs affiliates migrations + `IpHasher`.
2. **Token resolution skips eligibility.** `resolveLinkFromShareToken()` checks token format only —
   not `AffiliateLink::isEnabled()` / `affiliate->canBeAttributed()` — and the reuse branch silently
   clears `deactivated_at`, so requesting a payload can revive a disabled link. Fix: enforce
   eligibility on resolve *and* on active-cookie consumption; stop auto-reactivating.
3. **Live cleanup exceeds its boundary.** `User::deleting` → `deleteUserTracking()` deletes *whichever*
   affiliate the user owns — including a genuine commercial profile with all its data.
   Fix: restrict cleanup to provably share-generated profiles (e.g. `majlis_*` markers); give
   commercial deletion its own policy. (Snapshot/restore mitigates but doesn't scope.)
4. **Retry race double-applies effects.** On concurrent duplicate outcomes the package returns a
   replayed conversion, but the adapter still increments the link counter and calls the
   non-idempotent bridge. Fix: distinguish replay from creation (single atomic boundary), add stable
   idempotency keys to bridge payloads.
5. **Landing path has no dedupe.** Every `?share=` landing appends a touchpoint + bridge event, even
   with the same cookie; only the navigated branch dedupes. Fix: apply the same visit identity/window
   on the landing branch (atomically, not just an existence check).
6. **Purger orphans + wrong defaults (latent — zero prod callers, disabled by default).**
   `AffiliateRuntimeDataPurger` truncates 29 tables via `DB::table` (bypasses `AffiliateLink::deleting`
   → orphaned `tracked_links`/clicks) and its fallback table names don't match package defaults.
   Fix before ever enabling, or delete it.
7. **Bridge omits dedupe keys + attribution context (latent — no readers today).** No
   `idempotency_key`/`source_event_id` (package recorder has them), path/URL set to `subject_key`
   instead of the landing URL, no UTM passthrough. Fix if the signals leg is kept (see §4).

## 4. Write-only legs — stop writing what nobody reads (2/2)

- **Affiliate→signals telemetry.** `affiliate.attributed` / `affiliate.conversion.recorded` events and
  their `acquisition`/`conversion` categories are written on every landing/outcome and read **nowhere**:
  not by share analytics (reads affiliate tables), not by product insights (reads page/search events),
  not by any report. Cost: write amplification per request + misleading future data (per-landing
  cardinality, §3.5). **Recommendation:** stop the bridge's affiliate-event writes *and* disable the
  package's `signals.integrations.affiliates` listeners until a reader exists. Keep the signals package
  itself — browser/mobile telemetry and product insights are genuinely used.
- **Links rows.** Every share link allocates a `Link` row + `tracking_url` short URL; the app never
  reads `tracking_url`, never routes recipients through `/go/{slug}`, never handles `LinkClicked` for
  share. Tables migrated + public route registered for zero benefit. **Recommendation:** make links
  optional in `CreateTrackingLink` (commerce change: skip `CreatePublicLink` when
  `affiliates.links.enabled=false`) so ledger-only consumers stop paying the tax. Do not half-adopt
  `/go` URLs — pick direct-`?share=` (current) or real redirect, not both.

## 5. Bypass inventory — each package seam ilmu360 skips, and the verdict (2/2)

| Bypass | Consequence | Verdict |
|---|---|---|
| Direct `Affiliate` create (not `CreateAffiliate`) + hand-rolled codes (not `GenerateAffiliateCode`) | Misses approval-mode/status/code-retry/contact workflow; share users appear as active affiliates | **Keep, but mark**: share profiles are intentional; add an explicit share-marker the code (not just descriptions) can rely on, and stop `findAffiliateForUser` from adopting real commercial profiles |
| Hand-rolled attribution (not `TrackAffiliateVisit`/`AttachAffiliateToCart`) | Misses eligibility + rate-limit/self-referral/fingerprint/click checks; no `AffiliateAttributed` dispatch (package webhooks + signals registrar blind) | **Keep with hardening**: no-cart flow can't use cart actions; but add eligibility + self-share policy explicitly, and document last-share-wins as the chosen semantic |
| Direct `AffiliateLink` mutation (not `UpdateTrackingLink`) + post-create `custom_slug` | Works, but couples to fillable internals; auto-reactivates deactivated links | **Fix the reactivation** (§3.2); the post-create slug assignment is the sanctioned workaround for `prohibited` inputs — keep |
| `dispatch_event=false` on outcomes | Suppresses `NotifyConversionRecorded`, `AutoApproveOpenAffiliate`, package signals listener. Fraud + accounting still run (verified) | **Keep**: suppression is load-bearing (notifications default ON, communications installed — dispatching would spam sharers). Rename the misleading `dispatchAffiliate*` bridge methods to `record*ViaBridge` |
| Direct touchpoint creates | Bypasses nothing structural (no touchpoint action exists) — but writes raw IPs | **Keep, fix IPs** (§3.1) |
| 23 of 29 affiliate tables migrated-but-unused (programs/ranks/payouts/tax/upline/…) | Carry cost: migrations, purger surface, conceptual noise | **Accept**: deleting package tables per-consumer isn't supported; note as the price of ledger reuse |

## 6. Brand ↔ mechanism leaks (exact seams) (2/2)

| Direction | Leak | Fix |
|---|---|---|
| Brand → seam | `ShareTrackingContract::recordOutcome()` requires `DawahShareOutcomeType` | Contract takes a brand-free outcome key (string enum in `ShareTracking` namespace); Dawah layer maps product actions → keys |
| Brand → config | `config/dawah-share.php` owns TTL/cookie/dedupe/purge/bot settings | Acceptable *if documented*: rename mentally to "feature config"; or split `share-tracking.php` (mechanism) vs route/UI naming. Don't churn without need |
| Brand → mechanism | `ShareTrackingUrlService` imports concrete Event/Institution/Person/Series/Reference models + Dawah enums to classify URLs | Keep (URL→subject mapping is inherently app-specific) but name it honestly: it's an app-domain adapter, not generic URL plumbing |
| App → signals | Bridge hard-selects global `ilmu360` property slug | Keep with comment: single-tenant global property is a deliberate product decision, not an accident |
| Backend → API | DTOs expose `backend: 'affiliates'`; controller injects concrete facade, not the contract | Inject the contract; keep `backend` only if a second backend ever exists (otherwise delete the field) |
| Backend → telemetry | Share facts named `affiliate.*` in signals | Moot once §4 stops the writes; if reintroduced, use `share.*` names |
| Honest brand | `DawahShareController`, `dawah-share-panel`, `DawahImpact`, `/kongsi/*` | Keep — this is exactly where brand belongs |

Path-vocabulary wart (cosmetic): web `/kongsi/*` vs route names `dawah-share.*` vs API `/share/*`
for one controller. Pick one public vocabulary when convenient; not worth a migration today.

## 7. Owner scoping: ceremony without enforcement (verified 1/2, accepted 2/2)

ilmu360 ships **no** `config/affiliates.php` and **no** owner-related env vars, and all package
defaults are `enabled=false` (`affiliates.owner`, `signals.owner`, commerce-support master). So
`OwnerScope::apply()` early-returns everywhere: every `OwnerContext::withOwner(...)` /
`withoutGlobalScope(...)` in the adapter and bridge is currently **inert**. Real isolation rests on
manual filters — owner check in `findAffiliateForUser`, affiliate-ownership check in the authed token
resolver, cookie-as-capability for attribution, `whereNull` globals in the bridge — which are sound
but implicit. Two consequences: (a) don't mistake the ceremony for a boundary in review; (b) flipping
owner flags on later is **not** a safe default — it needs a compatibility audit first (e.g. the
global-unique `cookie_value` vs owner-scoped lookup interaction).

## 8. SWOT

**Strengths.** Contract seam with a null implementation (real, if single-adapter, seam); 66 green
share tests incl. ownership, idempotency, and telemetry-failure cases; reads centralized behind one
analytics facade; lazy link creation + reuse verified by test; internal-URL guard; HttpOnly/SameSite/
encrypted share cookie; fraud engine genuinely reused (reject-on-fraud runs on every outcome);
telemetry is fail-soft (bridge exceptions can't break sharing).

**Weaknesses.** Seven defects (§3), worst being live privacy (raw IPs), live over-broad cleanup, and
missing eligibility; two write-only legs (§4); contract is fat (14 methods), brand-typed, and
covers writes only; analytics bypasses the contract; fraud signals collected but invisible to admins;
`clicks` counter means outbound attempts while rows mean events (dual representation); misleading
`dispatch*` names; owner ceremony without enforcement.

**Opportunities.** Stop both write-only legs for immediate simplification; surface fraud in admin
analytics (data already collected); split + de-brand the contract; make links optional in the
affiliates package (helps every ledger-only consumer); optionally adopt `engagement.Share` for
outbound actions later; document the 3-layer naming (feature `dawah-share` / mechanism
`share-tracking` / brand `dawah`) so new files stop guessing.

**Threats.** Raw-IP columns grow on every request (privacy/retention); user deletion can wipe a
commercial affiliate aggregate today; retry/landing duplicates skew counts and any future signals
consumer; package upgrades already bit once (`1851cba1` proves the adapter tracks package internals
closely); both repos move fast under review (HEAD-pin all future analysis); enabling owner scoping
without an audit would break attribution lookups.

## 9. Way forward (phased; agreed 2/2)

**Phase 0 — stop the bleeding (days).** Fix §3.1–§3.5 in place: `IpHasher` at 3 write sites;
eligibility on token resolve + cookie consumption; narrow `deleteUserTracking` to share-marked
profiles; atomic outcome boundary + replay-vs-create distinction; landing dedupe. Add regression
tests for each (concurrency, disabled token, commercial-profile cleanup).
**Phase 1 — stop write-only work (days).** Remove bridge affiliate-event writes; disable
`signals.integrations.affiliates` listeners; file the commerce change making `links` optional in
`CreateTrackingLink`. Fix-or-delete the purger (§3.6).
**Phase 2 — straighten the seams (1–2 weeks).** Split the fat contract (capability/link/capture/
outcome), de-brand it (outcome key enum without `Dawah`), inject the contract (not the facade) in
the controller, rename `dispatch*` bridge methods, document last-share-wins + single-tenant global
property + inert-owner-ceremony decisions where they live.
**Phase 3 — only if triggered.** Extract app-owned share tables **iff**: share and commercial records
coexist with irreconcilable lifecycle/retention needs, or share behavior repeatedly forces exceptions
to affiliate invariants, or a second consumer needs independent attribution. Preserve the fraud rules
(by separating evaluation from ledger access) and keep the 66 tests as the behavioral spec.
**Explicitly not doing:** adopting `engagement.Share` now (wrong semantics: no attribution/cookie/
outcome); dispatching package affiliate events from the share flow (would spam notifications);
enabling owner scoping without an audit; renaming `/kongsi` routes (cosmetic churn).

Target map: **affiliates** = attribution/outcome ledger + fraud; **signals** = telemetry sink for
browser/mobile/product (affiliate leg off until a reader exists); **links** = optional redirect
service; **ShareTracking** = thin adapter + URL mechanics + analytics reads; **Dawah*** =
presentation + product-action→outcome mapping. UI stays exactly as users know it
(Share panel, Dawah Impact, Share Analytics) — the cleanup is behind the seam, not in front of it.

## Appendix — dual-research record

- **Luna's unique finds (verified by Muse):** raw-IP defect; `TrackAffiliateVisit`/`CreateAffiliate`/
  `GenerateAffiliateCode`/`UpdateTrackingLink` bypasses; package recorder idempotency keys the bridge
  omits; retry race via replay path; last-share-wins overwrite; mobile-attribution gap; counter/row
  dual representation; `engagement.Share` pointer.
- **Muse's unique finds (accepted by Luna):** affiliate→signals leg has **zero readers**
  (write-only, not just duplicated); links leg likewise unread; owner scoping fully off (ceremony
  inert); `deleteUserTracking` has a **live** prod caller (`User::deleting`) — cleanup risk is live,
  not latent; fraud **does** run despite `dispatch_event=false`; mid-review `1851cba1` fix.
- **Luna corrections accepted:** cleanup live (was: latent); owner framing (was: conditional bypass
  analysis); prohibited-inputs finding withdrawn (fixed at HEAD).
- **Converged recommendation:** harden-and-keep. Luna's initial extract-now position was revised
  after the cost/threshold debate; extraction threshold criteria (§9 Phase 3) are joint.
- Raw transcripts: `/tmp/luna-share-research.md` (246 lines), `/tmp/luna-rebuttal.md`;
  Muse scratch: `/tmp/claude-share-findings.md`.
