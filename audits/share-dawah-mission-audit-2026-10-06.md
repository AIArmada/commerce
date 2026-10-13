# Share & Dawah Impact — Mission-First Re-Audit + SWOT

- **Date:** 2026-10-06
- **Scope (unchanged from the technical audit):** ilmu360 share feature (`ShareTracking*`,
  `DawahShare*`, `DawahImpact*`, `AffiliateSignalsBridge`, share analytics) and the commerce
  packages + integrations + concepts + brand seams behind it (`affiliates`, `signals`, `links`,
  `engagement`, owner scoping, events).
- **Pinned to:** ilmu360 `ef06973c`, commerce `da0ed0c6a`.
- **Method:** dual independent research — Muse + `gpt-6-luna` (max effort, Codex CLI, read-only) —
  with claim-by-claim verification against source. The lens is deliberately flipped: every judgment
  below starts from the human/community outcome and works back to the code. Mission sources:
  `PRODUCT.md` (trustworthy/warm/scholarly; anti-gamification, anti-dark-patterns),
  `DESIGN.md` (WhatsApp is the distribution layer; generated share cards; **real numbers only** —
  "an inflated number is a trust failure"; mobile-first).
- **Relation to the technical audit** (`share-dawah-affiliates-signals-links-review-2026-10-06.md`):
  its verified defects stand and are re-ranked here by mission risk (§5). Its *framing* is
  superseded: "dead tables," "abuse," and "delete unread telemetry" were code-centric verdicts.
  This report re-derives each one from mission value. Rankings marked **(2/2)** were verified by
  both researchers.

## Verdict

The share system already serves the mission's core loop — share on WhatsApp, get attributed,
see private impact — and its data model is a genuine head start on the rewards future. But it
currently **counts more than it inspires, and stores more than it can honestly prove**: no sharer
ever learns "someone came because of you" unless they open a dashboard; visit counts can inflate
(which your own design doc calls a trust failure); raw visitor IPs sit in hash-only columns; and
the rewards machinery (ranks/tiers/payouts) is present but money-shaped, unfit for dawah
recognition as-is. The way forward: **protect trust first** (honest numbers, minimal visitor data —
see §5), **close the inspiration loop** (moments, stories, community proof — §3), then **grow into
rewards** on the ledger you already have (§2). No extraction, no rewrite; the mission needs
*more* from this architecture, not less of it.

## 1. Mission map — human outcomes, what exists vs what's missing (2/2)

| Human outcome | EXISTS in product | MISSING |
|---|---|---|
| **Sharer feels sharing mattered** | Private impact dashboard: visits, unique visitors, signups, registrations, check-ins, submissions, per-link daily performance, provider breakdown, "Recent Responses" feed ("Registered for an event"); dashboard frames sharing as *Sumbangan Saya* (My Contribution) with a *"Sebarkan ilmu, luaskan manfaat"* invitation | No outcome *moments* (nothing tells a sharer when someone responds); no milestones/recognition; no community context ("part of 12,400 visits"); labels over-claim (*Manfaat Diterima* = unique visitors — a visit doesn't prove benefit) |
| **Community finds useful content** | Share panel on public pages: native share, copy link, 8 providers (WhatsApp first); per-channel tracking; guest sharing with zero friction; internal-URL guard | Generated 1200×630 branded share cards (DESIGN-required; og:image falls back to posters); short marketable URLs (links package writes them, share flow ignores them); richer WhatsApp template (currently plain text+URL) |
| **Sharing becomes a growth loop** | Outbound → landing → outcome fully instrumented; signup captured on web + social; 30-day attribution cookie; provider breakdown shows what channels work | No share→signup→*first-share* measurement (can't see recipients become sharers); no guest-sharer→account continuity (anonymous profiles never merge on signup); no campaign-quality view for marketing |
| **Admins steward growth** | Totals, provider breakdown, top sharers/links, recent visits/outcomes; fraud engine scores every outcome | No journey/funnel view; fraud signals invisible in admin UI (collected but never surfaced); no reward-eligibility review queue; API exposes visitor keys/URLs/actor IDs (over-exposed recipient trail) |
| **Rewards-ready foundation** | Every sharer is an Affiliate; outcomes are typed conversions; fraud + idempotency + ranks/tiers/programs/payouts/balances ledgers migrated | No Dawah program/tiers enrolled; rank qualification is revenue-shaped (reads zero on zero-value outcomes); no award ledger, eligibility rules, reversals, or recognition surface |

## 2. Rewards readiness — head start with conditions (2/2)

Affiliates-as-ledger is a **head start, not a trap** — provided rewards are designed as dawah
recognition, not switched on as commerce:

- **What transfers directly:** sharer identity + ledger, typed outcomes (`DawahShareOutcomeType`'s
  11 actions), idempotent recording, fraud scoring with reject-on-fraud, rank/tier/program/payout/
  balance machinery, scheduled rank processing. Zero commission today is correctly read as
  "rewards not activated," not abuse.
- **What does NOT transfer:** flipping `commission_rate` alone pays nothing — the outcome path
  records zero amounts and never runs the purchase commission calculator. Rank qualification sums
  30-day revenue + downlines: on zero-value outcomes every sharer reads zero. Sales semantics
  (downlines, revenue tiers, percentage-of-nothing) must not leak into dawah recognition.
- **What rewards need (new):** a Dawah award policy — outcome quality tiers (confirmed attendance ≫
  save), self-credit rules, caps, bot handling, reversal/cancellation handling, accepted-vs-submitted
  distinction; a versioned **award ledger** (recorded → qualifies → approved → paid/reversed),
  separate from the fact ledger; fixed micro-awards (points/MYR) per eligible event, not percentages.
- **Brand bound (PRODUCT.md):** no game-y UI, no manufactured urgency, trust earned. Recognition must
  feel scholarly: gratitude, milestones, community proof — never public shaming leaderboards,
  streak pressure, or point-mongering. Admin-only `top_sharers` can stay private fuel for *thank-you*
  actions, not a public scoreboard.

## 3. Inspiration gap — from counts to felt impact (2/2)

What exists (private dashboard + responses feed) reassures an already-motivated sharer but gives
little reason to share *again*. Ordered by mission leverage, cheapest first:

1. **Sharer moments (biggest gap).** Nothing notifies a sharer on signup/registration/check-in via
   their link — the "your share brought someone" moment doesn't exist outside dashboard pull.
   Add gentle, grateful notifications (calm copy, no urgency), plus live micro-proof in the share
   panel ("3 people visited from your shares"). Data already stored; purely a delivery gap.
2. **Honest labels.** *Manfaat Diterima* (benefit received) for unique visitors over-claims — a visit
   doesn't prove benefit or learning. *Total Responses* blends unequal actions. Relabel precisely
   (browser visits / registrations / confirmed attendance) with definitions; DESIGN.md demands it.
3. **Outcome stories from stored data.** Subject + channel + outcome type already support truthful
   cards: "Your Kuliah Maghrib link brought 12 visits; 3 registered; 2 checked in." Keep recipient
   identities private; distinguish submitted vs accepted. No new instrumentation needed.
4. **Community proof.** Admin sees totals; sharers see only themselves. Add privacy-safe community
   context ("members helped 240 people find this month's majlis", honest window) — belonging
   motivates more than private counts, and DESIGN wants real-numbers proof.
5. **Calm milestones.** Timestamps already support "first attendance from your link" / "five majlis
   found through you." Occasional, optional, grateful — never streaks, never public ranks.
6. **Beautiful shares.** Generated 1200×630 cards (per DESIGN) + short branded URLs (links package,
   currently written-but-unused) make every WhatsApp share an act of pride, not a bare link.

## 4. Growth loops — measured vs missing (2/2)

| Loop stage | Measured today | Missing |
|---|---|---|
| Share → landing | Outbound actions, token landings, visits, channel/origin on every record | Outbound action ≠ delivered message (no proof of receipt); signals stream lacks an explicit outbound-share event, so share→land conversion needs ledger joins |
| Landing → signup/outcome | Signup on web + social, 11 outcome types, 30-day cookie | No landing→signup cohort report; no signup-quality or signup→first-share tracking |
| Recipient → next sharer | Dashboard invitation (*Sebarkan ilmu…*), link reuse | Not instrumented as a handoff — can't measure whether converts become sharers |
| Guest → user | Guest can share (anonymous profile) and guest landings convert to signups | **Guest-sharer identity dies on signup** — no anonymous-profile→account merge, so early sharing never joins the personal dashboard or reward identity |
| Channel → marketing decisions | Provider breakdown (outbound/visits/visitors/signups/responses) for user + admin | No campaign journey / quality-normalized view; no guest-vs-authed segmentation; origin/referrer/query metadata collected but unrefined into decisions |

Recommended instrumentation: immutable share-action IDs; intent vs platform-handoff distinction;
signup→first-share tracking; guest-merge on signup; campaign-quality rollups by channel/origin/
content-type. Keep delivery claims and raw identities out of these metrics.

## 5. Technical findings re-scored by mission risk (2/2)

| Rank | Finding | Mission risk |
|---:|---|---|
| 1 — Critical, now | **Inflated + mislabeled impact.** Landing path has no dedupe (repeat `?share=` visits append touchpoints + signals events); attribution upsert overwrites sharer/link fields (last-share-wins, undocumented); *Manfaat Diterima* over-claims visits as benefit | Direct trust failure per DESIGN.md ("an inflated number is a trust failure"); miscredited outcomes demotivate the true sharer |
| 2 — Critical, now | **Visitor-data over-collection.** Raw IPs in hash-only columns; user-agent, landing URL, referrer, all query params stored; API returns visitor keys/URLs/metadata/actor IDs | A religious-interest trail with raw identifiers — an *amanah* (trust) failure toward visitors and sharers alike. Minimize: hash IPs, whitelist campaign params, set retention, return aggregates |
| 3 — High, now | **Over-broad cleanup (live).** Every user deletion wipes whichever affiliate profile they own, commercial or share-made | Stewardship failure; snapshot/restore mitigates recovery, not scope. Narrow to share-marked profiles |
| 4 — High, now | **Missing eligibility.** Token resolve ignores link deactivation/affiliate eligibility; reuse auto-reactivates; no self-share policy | Gaming + inflated credit poison future rewards and erode sharer trust in fairness |
| 5 — High before rewards | **No Dawah eligibility/reversals.** All outcomes recorded approved; no quality tiers, self-credit, cancellation, or accepted-vs-submitted rules | Launch blocker for fair rewards (not current abuse — nothing is paid out) |
| 6 — Opportunity | **`affiliate.*` signals unread.** Journey/storytelling data with no consumer, and unfit mapping (per-landing cardinality, no idempotency keys, weak identity) | Not dead telemetry — *future* storytelling fuel. Fix mapping only alongside a named reader (journey explorer); until then, stop blind writes |
| 7 — Opportunity | **Links written, never followed.** Short URLs + `/go` route exist; share flow uses long `?share=` URLs | Missed marketing asset for WhatsApp tidiness. Either carry the share token through real short links or make links optional — don't keep paying for rows nobody follows |
| 8 — Roadmap, not debt | **~23 affiliate tables with no host-app usage** (ranks/tiers/programs/payouts/…) | **Re-scored: rewards roadmap, not dead weight.** Keep; team-sales/upline and support/training/tax stay dormant until a concrete program needs them |

Global purge stays as previously assessed: disabled, zero prod callers — correct before ever enabling.

## 6. Target experience + minimal architecture (2/2)

**Sharer:** one clear share action per page producing a clean branded short URL + card; impact
dashboard opening with a truthful period summary ("18 browser visits; 4 registered; 2 checked in",
estimates labeled as estimates); per-item activity stories with private visitors; occasional calm
milestones; and — the missing heartbeat — grateful outcome moments when someone responds through
their link. If rewards launch: published rules before enrollment, and recorded → qualifies →
approved → paid states kept visibly separate from raw actions.

**Admin/marketing:** share→outcome funnel by provider, origin, campaign, and content type with
rates, definitions, freshness, and qualified categories; fraud-visible outcomes; a reward-eligibility
and reversal review queue; person-level visitor data kept out of routine stewardship views.

**Minimal architecture to serve it — build / keep / stop:**
- **Build:** sharer-moment notifications + panel micro-proof; honest labels + community totals;
  generated share cards; guest-profile merge on signup; signup→first-share tracking; Dawah award
  policy + versioned award ledger; landing dedupe + documented last-share-wins; eligibility checks.
- **Keep:** affiliates as the share link/attribution/conversion/fraud ledger (and future payout
  operations); signals for aggregate funnel/journey views *after* fixing recipient identity,
  cardinality, and idempotency keys; links for branded redirects *after* carrying the share token
  through (plus review of its IP/UA collection); `engagement.Share` only if outbound intent-vs-
  handoff semantics are defined — never as proof of delivery.
- **Stop:** raw-IP writes; mutable-cookie-as-history; treating visits and all outcome types as
  equally strong; blind signals writes without a named reader; global purge anywhere near
  production paths.

## 7. Mission SWOT

**Strengths.** Sharing lives where the community already is (WhatsApp-first panel, zero-friction
guest sharing); shares already connect to landings and 11 real community actions; private impact
reporting respects sharer privacy on the web surface; admin already sees provider/top-sharer/top-link
intelligence; fraud scoring guards every outcome; 66 tests pin the behaviors users feel.

**Weaknesses.** Counts without stories or moments — inspiration requires dashboard pull; labels
over-claim (*Manfaat Diterima*); landing replay + mutable attribution distort the numbers the
mission runs on; share URLs ignore the short-link asset; the analytics stream can't show the full
loop (no outbound-share event, no recipient→sharer tracking, no guest continuity).

**Opportunities.** Truthful story cards + community totals from already-stored data; branded short
links + generated cards for WhatsApp pride; channel/campaign intelligence for outreach; calm
milestones and gratitude moments; fraud visibility for admins; and the big one — a transparent,
scholarly-framed recognition/rewards pilot on the ledger that's already built.

**Threats.** Religious-interest trails + raw identifiers erode the community's *amanah*; inflated or
miscredited impact discourages the very sharers the mission needs; sales-style ranks, cash for
low-quality actions, public leaderboards, or streak pressure would make dawah feel transactional
and violate the brand's scholarly credibility; package internals drift under the adapter (proven
mid-review); enabling owner scoping or rewards without dedicated audits would break attribution
and fairness respectively.

## 8. Exact execution plan (reconciled 2/2)

## Goal

Harden the share system into a trustworthy, inspiring, rewards-ready mission asset — keeping the
affiliates ledger authoritative and dawah/share a strict wrapper — without extraction or rewrite.

## Success Criteria

- No raw visitor IPs written; stored recipient trails minimized; honest labels everywhere.
- Every displayed number is defined, deduped, and reproducible from eligible rows after a declared
  reporting start timestamp; no retrospective relabeling of old counts.
- Disabled links earn nothing; user deletion can't wipe commercial profiles; one business outcome
  yields one credit, one count, one telemetry effect.
- Sharers get calm outcome moments; community totals are privacy-safe; WhatsApp shares carry
  accurate branded cards; guest sharing survives signup.
- No blind signals writes; links allocation optional; award policy + ledger ready for a
  recognition pilot (no money). All 53+ share tests green plus new regressions.

## Approach

Trust → truth → stewardship → inspiration → recognition, in 7 independently releasable phases
(~34–47 dev-days, one senior dev). Founder decisions gate measurement rules (item 1), engagement
verdict (16), and recognition policy (22). Wrapper discipline: APP items use package public API
only and must survive `composer update 'aiarmada/*'` within the supported range; the only PKG
items are 8-split and 11, each with package tests + docs.

## Steps

**Phase 1 — Protect trust (L, 6–8d).** 1.[DECISION/APP] Approve attribution + measurement rules
(last eligible landing wins per browser, 30-day expiry unrenewed by navigation, 5-min rolling
dedupe, outbound≠delivered, outcome categories stay separate, reporting start timestamp).
2.[SHARPENING/APP] Honest labels (*Pelayar unik* + definitions; precise outcome wording; submitted
vs approved; ms.json). Depends 1. 3.[BUGFIX/APP] `IpHasher` at all 3 write sites; whitelist
campaign metadata; strip recipient trails from sharer responses; 30-day retention rule (no silent
rewrite of old rows). 4.[BUGFIX/APP] Eligibility on token resolve + cookie consumption, rechecked
in transactions; remove auto-reactivation; disabled URLs still open publicly but earn nothing.
5.[BUGFIX/APP] `metadata.share_tracking_profile` marker on new share profiles; never adopt
unmarked commercial profiles; cleanup requires marker + ownership; restore coverage. 6.[REFACTOR/APP]
Delete the global purger + config + tests; add preservation test.

**Phase 2 — One trustworthy ledger effect (L, 7–10d).** 7.[REFACTOR/APP] Remove bridge + its
`dispatch*` methods; disable app affiliate-signals integration; keep package recorder.
8.[BUGFIX/SPLIT] PKG: `RecordAffiliateOutcome` returns explicit created/replayed result (generic,
additive). APP: unique outcome receipt `(outcome_type, outcome_key)`; creation+fraud+accounting+
receipt+single counter mutation in one transaction with retry. Depends 4,5,7.
9.[BUGFIX/APP] One transactional visit recorder for landing + navigation; dedupe by browser
capability+link+URL+window; different-sharer landing creates a NEW attribution (no rewrite).
Depends 1,4,7. 10.[BUGFIX/APP] Analytics from touchpoints/conversions only (counters are
projections); exclude rejected/reversed; separate submitted/approved and registration/attendance;
start timestamp everywhere. Depends 1,8,9. 11.[REFACTOR/PKG] New generic `CreateAffiliateLink`
base action (validation + creation, no links); `CreateTrackingLink` composes it and adds the
short URL; app share calls the base action. Package tests with/without Links installed + docs.
Depends 4,5.

**Phase 3 — Boundaries + stewardship (M, 4–5d).** 12.[REFACTOR/APP] Split contract
(capabilities/link/attribution/outcome/lifecycle/analytics-reads); rename enum to
`ShareOutcomeType` (values kept); Dawah stays in presentation. Depends 8,11.
13.[REFACTOR/APP] Drop `backend` from DTOs; inject focused contracts; migrate callers in bounded
commits, then delete facades (phased, defined endpoint). Depends 12. 14.[SHARPENING/APP]
Document inert owner ceremony + actual manual boundaries; pin disabled config in tests; owner
enablement explicitly out of scope. Depends 4,5,12. 15.[FEATURE/APP] Admin fraud visibility +
guarded `ReviewShareFraudSignalAction` (dismiss/confirm with reviewer/time/reason); review alone
never approves/awards. Depends 8,10. 16.[DECISION/APP] Record no-adopt on `engagement.Share`
(no attribution/delivery semantics); revisit trigger documented.

**Phase 4 — Inspiration + growth loops (L, 8–10d).** 17.[FEATURE/APP] Guest-share claim on signup
(`ClaimGuestShareProfileAction`, encrypted capability, association not owner-rewrite; exactly one
claimer; historical profiles stay unclaimed). Depends 5,12,14. 18.[FEATURE/APP] Signup→first-share
funnel WITH its reader (immutable action IDs, client idempotency, product-signals schema + admin
view; ledger stays authoritative). Depends 8,13,17. 19.[FEATURE/APP] Gentle outcome moments
(first acknowledgement + daily digest max; comms/inbox machinery; no per-visit pings) + panel
micro-proof; calm copy, prefs/quiet hours, no recipient identity. Depends 10,15,17.
20.[FEATURE/APP] Community totals (30-day window, freshness, ≥10-sharer suppression, honest empty
state; never sum unlike actions as "people helped"). Depends 10,17. 21.[FEATURE/APP] Generated
1200×630 share cards (tokens, verified speaker/org, schedule, venue, cancelled/changed states;
revision-keyed cache; no tokens/private counts in cached images) + OG wiring + panel download.
Depends 11,13.

**Phase 5 — Recognition policy (S, 1–2d spec).** 22.[DECISION/APP] Founder approves versioned
Dawah policy: quality tiers (connection/participation/contribution), self-credit, caps, reversals,
appeal, private acknowledgement; recognition WITHOUT money/points; monetary program explicitly
deferred with its own future decision. Depends 10,15,17,19.

**Phase 6 — Recognition ledger (L, 8–10d).** 23.[FEATURE/APP] `DawahAwardPolicyVersion` (immutable)
+ `DawahAward` + append-only `DawahAwardEntry`; uniqueness on recipient+outcome+family (not
version); no backfill. Depends 22. 24.[FEATURE/APP] `EvaluateDawahAwardAction` from committed
outcomes + real verification transitions (checks underlying records, reuses package fraud);
holds for review queue. Depends 8,15,17,22,23. 25.[FEATURE/APP] First-class reversals
(`ReverseShareOutcomeAction` + `ReverseDawahAwardAction`; package reversal action reused;
append-only reversal entries; projections refreshed). Depends 10,23,24. 26.[FEATURE/APP] Private
recognition pilot (enrolled cohort; qualifying category/subject/date/policy shown; pending vs
reversed separated; gratitude copy, no levels/bars/badges/countdowns). Depends 19,24,25.

**Phase 7 — Release (S, 1–2d).** 27.[HARDENING/APP] End-to-end journey verification (WhatsApp →
landing → signup → claim → first share → outcome → moment → totals → recognition → reversal →
deletion); Pest suites + Pint + scoped static analysis on PGSQL + MariaDB race paths; weekly
stewardship ritual (fraud holds, stuck effects, reconciliation). Depends 1–26.

## Validation Plan

- Pest `--parallel` throughout; preserve/extend the 53 share cases; new regressions per item
  (concurrency on PGSQL/MariaDB with independent connections, not SQLite alone).
- Focused manual checks: Malay phone review (labels, moments, cards, WhatsApp unfurl iOS+Android);
  hand-reconcile a fixture ledger against dashboards; disabled-URL, guest-claim race, and
  delete/restore drills. Highest-risk validation: item 8's forced-collision race suite.
- No backfills, aliases, dual writes, historical relabeling, or notification/award replays.

## Risks / Open Questions

- Founder decisions 1/22 gate phases 2/6 — schedule them early to avoid idle time.
- Open questions: none. All other questions were resolved from the workspace; no external claims made.

## Not doing

No extraction; no `/go` adoption without measured need; no blind bridge repair (item 18 replaces
it purposefully); no `engagement.Share`; no owner-scope enablement; no commission/revenue-rank/
downline/payout rewards; no public leaderboards/streaks/XP/urgency; no learning/attendance claims
from weak evidence; no IP-similarity guest merge; no historical rewrite/replay; no new fraud
engine or notification transport.

## Appendix — dual-research record

- **Scope held:** same codebase, packages, integrations, concepts, brand, and features as the
  technical audit; only the judgment criterion flipped (code quality → mission value).
- **Luna's unique finds (verified by Muse):** *Sumbangan Saya* / *Manfaat Diterima* /
  *Sebarkan ilmu* dashboard copy and metric-honesty gap; API over-exposure of visitor keys/URLs/
  actor IDs; guest-merge gap; no share→signup→first-share funnel; rewards needs award ledger +
  quality tiers (not flipped commission); engagement-share semantics caveat; PRODUCT.md
  anti-gamification bound on recognition design.
- **Muse's unique finds (consistent with Luna):** per-link daily performance + responses feed depth
  (inspiration engine exists); og fallback gap (no generated cards); rank metrics read zero on
  zero-value outcomes (impact-shaped qualification needed); no sharer notifications at all;
  no community totals for sharers; WhatsApp template plain vs DESIGN intent.
- **Corrections to report 1:** purger lists **28** tables (not 29); "~23 unused" hedged to "no
  host-app usage" (package-internal writes possible — DB contents not inspected); prohibited-inputs
  finding already resolved at HEAD by `1851cba1`.
- Raw transcripts: `/tmp/luna-mission-audit.md` (120 lines); Muse scratch:
  `/tmp/claude-mission-findings.md`. Technical audit + transcripts (`/tmp/luna-share-research.md`,
  `/tmp/luna-rebuttal.md`, `/tmp/claude-share-findings.md`) retained for the defect-level record.
- §8 plan: Luna's 27-item plan (`/tmp/luna-plan.md`, 339 lines) reconciled with Muse's 20-item
  plan (`/tmp/claude-plan.md`); placement verdicts agreed in a follow-up pass
  (`/tmp/luna-placement.md`). Luna accepted phased (not big-bang) facade removal and optional-now
  for short links; Muse adopted Luna's outcome-receipt design, immutable-attribution history,
  founder measurement decision, and guest-claim (not merge) design.
