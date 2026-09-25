# Can `aiarmada/links` replace the affiliate link-tracking implementations?

**Verdict: Neither — and for the network the question is already moot.** `aiarmada/links` is a
generic cloaked-redirect + click-capture package. The network (`packages/affiliate-network`)
already rides on it as its redirect substrate (`composer.json` requires `aiarmada/links`,
`OfferLinkService` mints one backing tracked link per offer link, `OfferLinkGate` implements
the gate seam, `IncrementNetworkLinkClicks` consumes `LinkClicked`). There is nothing left to
"replace" there. The engine (`packages/affiliates`) is a different shape — signed query-param
URLs plus cookie-upserted attribution rows plus a commission/fraud pipeline — and forcing it
onto redirect rows would break live links, explode click storage, and drag in redirect
machinery the engine deliberately avoids.

All claims below are sourced from package code, not from secondary write-ups.

## What `aiarmada/links` is

Source: `packages/links/{CONTEXT.md,composer.json,docs/01-overview.md,config/links.php,routes/web.php,src/**}`

- **Purpose**: "Generic tracked-link management: cloaked redirects, click capture,
  expiry/limits, domain events" (`composer.json`, `CONTEXT.md`). Owns link records, the
  cloaked redirect route (`GET /go/{slug}`, `routes/web.php`, `config/links.php` routing),
  click records, and lifecycle events (`docs/01-overview.md`).
- **Explicit non-goal**: "Inbound affiliate programs, commissions, or payouts; see
  `aiarmada/affiliates`" (`docs/01-overview.md`, "What this package does not own").
  The network is listed as a consumer: "rides on tracked links for offer redirects".
- **Link row** (`src/Models/Link.php`): name, globally-unique slug, destination_url,
  utm_defaults, parameters, require_signature, max_clicks, counter caches
  (total_clicks, human_clicks), expires_at, deactivated_at, first/last_clicked_at, plus
  owner + subject morph. `hasReachedClickLimit()` counts `human_clicks` only.
- **Click row** (`src/Models/LinkClick.php`): one row per hit — link_id, subject copy,
  occurred_at, ip, raw UA + device/brand/model/browser/version/os/version, is_bot,
  referrer, utm_* columns, properties (ad click IDs inside).
- **Redirect** (`src/Actions/RedirectToLink.php`): public lookup without owner scope
  (404), per-link signature check (403), `ResolveLink::blockedReason` check
  (deactivated/expired/limit_reached/gate reason → `LinkExpired` / `LinkClickLimitReached` /
  `LinkBlocked` + 410), then `RecordLinkClick` in try/catch — tracking failures are
  reported but never break the redirect. Destination merge: incoming UTM overrides,
  defaults fill gaps, ad click IDs pass through, link `parameters` always win so
  "request query strings can never spoof attribution values".
- **Click capture** (`src/Actions/RecordLinkClick.php`): UA parsed by
  `UserAgentParserInterface` (matomo/device-detector, `src/Support/DeviceDetectorUserAgentParser.php`),
  bot = parser flag OR `BotDetectorInterface` regex (`src/Support/DefaultBotDetector.php`).
  Bots recorded but excluded from `human_clicks` and limits by default
  (`config/links.php` tracking.bots: record true, count false). UTM/click-IDs taken from
  explicit context then request query. Fires `LinkClicked($link, $click)` (`src/Events/LinkClicked.php`).
- **Consumer seams**: subject morph (`src/Models/Concerns/HasSubject.php`,
  `scopeForSubject`), `LinkGateInterface::blockedReason()` (`src/Contracts/LinkGateInterface.php`,
  default allow-all `src/Support/DefaultLinkGate.php`), per-link signed URLs via
  `GenerateLinkUrl` (temporary signed route, TTL default 30d), full lifecycle events
  (`LinkCreated/Updated/Deactivated/Reactivated/Clicked/Expired/ClickLimitReached/Blocked`).
- **Dependencies** (`composer.json`): `commerce-support`, `lorisleiva/laravel-actions`,
  `matomo/device-detector`, `spatie/laravel-package-tools`. Stays dependency-free of
  sibling domains; third parties integrate by listening to events (`CONTEXT.md`).

## What the engine (`packages/affiliates`) is

Source: `packages/affiliates/{composer.json,config/affiliates.php,src/**}`

- **Links are signed query-param URLs, not redirect rows.** `AffiliateLinkGenerator::generate()`
  (`src/Support/Links/AffiliateLinkGenerator.php`) mints `?aff={code}&aff_exp={ts}&aff_sig={hmac}`
  (param name, 7-day default TTL, allowed-hosts, APP_KEY-derived signing key all in
  `config/affiliates.php` links). `verify()` checks expiry and `hash_equals`. The link
  carries the affiliate **code**, an **expiry**, and a **signature**; arbitrary `params`
  ride along. Program is a DB column, not a URL fact.
- **`AffiliateLink` is a campaign record with dead counters** (`src/Models/AffiliateLink.php`):
  affiliate_id, program_id, destination/tracking/short_url, custom_slug, campaign,
  sub_id x3, subject fields, origin, clicks/conversions counters, deactivated_at.
  `incrementClicks()` / `incrementConversions()` have **no callers anywhere in the
  package** (grep: only the definitions) — nothing ever increments them. Relations to
  attributions, touchpoints, and conversions exist for joins, not for redirect flow.
- **`CreateTrackingLink`** (`src/Actions/Affiliates/CreateTrackingLink.php`) requires an
  active affiliate, owner-guards the program, sanitizes subject metadata, and persists
  the row. It does not create anything redirectable.
- **`AffiliateApiController::links()`** (`src/Http/Controllers/AffiliateApiController.php:43-102`)
  validates url/ttl/params/subject_*, calls `CreateTrackingLink`, returns
  id/tracking_url/subject. No cloaking, no redirect, no click row.
- **Clicks become attribution rows, not click rows.** `TrackAffiliateCookie` middleware
  (`src/Support/Middleware/TrackAffiliateCookie.php`) reads `?aff=`/`affiliate`/`ref`/`referral`,
  calls `TrackAffiliateVisit::handle($code, $context, $cookieValue)`, and sets the
  `affiliate_session` cookie (30d default). The public `/r/{code}` entry route does the
  same (`src/Actions/Affiliates/CapturePublicAffiliateReferral.php`).
- **`TrackAffiliateVisit`** (`src/Actions/Affiliates/TrackAffiliateVisit.php`) is the real
  click pipeline: code must resolve to an active affiliate; then IP rate-limit (default
  off), self-referral block (default on, owner comparison), fingerprint-duplicate block
  (default off), and `FraudDetectionService::analyzeClick` must allow. Then it **upserts
  one `AffiliateAttribution` row per cookie** (UUID cookie, fingerprint-mismatch rotates):
  affiliate_id/code, subject_*, cart_*, cookie_value, voucher, source/medium/campaign/term/content,
  landing/referrer/UA, **hashed** IP (`IpHasher::hash`), user/visitor/channel/origin/link/type,
  fingerprint sha256(UA|IP), owner from affiliate, `expires_at = now + attribution_ttl_days`
  (30d default, `config/affiliates.php` tracking). `TouchAffiliateAttribution` refreshes
  `last_cookie_seen_at` and extends the window on repeat visits.
- **Attribution window is two-layered**: DB row expiry (`attribution_ttl_days`) plus
  browser cookie TTL (`cookies.ttl_minutes`). Both default to 30 days but are independent
  knobs (`config/affiliates.php`).
- **Fraud is behavioral, not UA-regex** (`src/Services/FraudDetectionService.php`,
  `src/Rules/ClickVelocityRule.php`): tagged `FraudRule`s sum risk points; click allowed
  iff score < blocking_threshold (100). `ClickVelocityRule` counts clicks per
  affiliate+hashed-IP in cache (100/hour default) and emits `AffiliateFraudSignal`
  rows + `FraudSignalDetected`. Siblings cover conversion velocity, fast conversion,
  fingerprint repeat, geo anomaly, self-referral. No device parsing, no bot list.
- **Conversions consume attribution, not clicks**
  (`src/Actions/Conversions/RecordAffiliateConversion.php`): resolves the active
  attribution by cart identifier/instance, computes commission (payload override >
  voucher override > program rule engine via `affiliate_program_id` > affiliate default),
  splits multi-touch weights, writes idempotent `AffiliateConversion` rows
  (idempotency_key on external_reference or cart+amounts hash), fraud-checks, posts
  accounting, allocates upline. The `affiliate_link_id` is an optional join key, never
  the driver.
- **Dependencies** (`composer.json`): only `contacting` + `commerce-support`. No
  `links`, no `laravel-actions`, no `device-detector`.

## What the network (`packages/affiliate-network`) is

Source: `packages/affiliate-network/{composer.json,config/affiliate-network.php,src/**}`

- **Already built on `aiarmada/links`.** `composer.json` requires `aiarmada/links`
  (`self.version`). The model docblock says it outright: "Redirect mechanics (slug,
  destination, signed URLs, click events) live on the backing tracked link; this row
  owns attribution facts and counters" (`src/Models/AffiliateOfferLink.php:20-24`).
  `OfferLinkService` repeats the boundary: "each offer link rides on one backing
  tracked link" (`src/Services/OfferLinkService.php:24-33`).
- **`AffiliateOfferLink`** (`src/Models/AffiliateOfferLink.php`): link_id, offer_id,
  affiliate_id (string FK to the configurable affiliate model), site_id, sub_id x3,
  clicks/conversions/revenue counters, currency, is_active, metadata. `isExpired()` and
  `trackedSlug()` delegate to the backing `Link`.
- **`OfferLinkService::createLink`** (`src/Services/OfferLinkService.php:48-92`):
  requires active offer, verified site, approval when the offer demands it; creates the
  attribution row, then a tracked link with a 16-char slug, `parameters = custom + {anl:
  slug} + sub1/2/3`, `require_signature = true`, subject = the offer-link morph, and
  `requireHttps = false` ("Merchants may still serve plain http"). Tracking URLs come
  from `GenerateLinkUrl` (signed, since the link requires it).
- **`OfferLinkGate`** (`src/Support/OfferLinkGate.php`) is the textbook gate consumer:
  ignores non-offer subjects, then returns `link_unknown` / `link_inactive` /
  `offer_inactive` / `site_unverified` / `not_approved`. Bound as the
  `LinkGateInterface` in `AffiliateNetworkServiceProvider:54`.
- **Clicks mirror from events** (`src/Listeners/IncrementNetworkLinkClicks.php`,
  registered at `AffiliateNetworkServiceProvider:62`): on `LinkClicked`, ignore
  non-offer subjects and `is_bot` hits, then increment the offer-link counter inside
  the affiliate's owner context. Bots are recorded by links but never inflate network
  counters — exactly the seam links designed for.
- **Cookie** (`src/Http/Middleware/TrackNetworkLinkCookie.php`): reads `?anl=`, resolves
  via `OfferLinkService::resolveLink` (global window, active link + active offer), and
  writes an **encrypted** `affiliate_network_link` cookie
  `{code, affiliate_id, offer_id, clicked_at}` — encrypted so `clicked_at` "cannot be
  forged client-side, regardless of whether EncryptCookies is active". 30d TTL. New
  code wins (last-touch). `parseCookie` returns null on any tamper.
- **Conversions, two paths**: checkout listener `RecordNetworkConversionForOrder`
  (only when `checkout.enabled`, default **false**) reads the cookie, re-resolves the
  link, enforces the `attribution_window_hours` window (720h default) on `clicked_at`,
  records via `OfferLinkService::recordConversion`, and stamps
  `order.metadata.network_attribution` for idempotency; the merchant postback
  controller `ReportNetworkConversionController` authenticates remote merchants by
  bearer catalog token, enforces site/link/offer validity, and is idempotent on
  (network link, external reference) via the ledger — redeliveries return existing
  state "without touching counters".
- **`RecordNetworkConversion`** (`src/Actions/RecordNetworkConversion.php`): guards
  currency mismatch (count the conversion, zero the revenue, warn-log), increments
  counters, fires `NetworkConversionRecorded`, posts per-currency ledger rows when an
  external reference exists.
- **Identity is a resolver, not a column.** There is no `user_key` column and no
  `network_click_id` column anywhere in `src/` (grep confirms). Identity is the
  `AffiliateIdentityResolver` contract: `UserKeyAffiliateIdentityResolver` for
  engine-less installs ("the auth user key doubles as the affiliate id and the user
  row doubles as the owner", `src/Support/UserKeyAffiliateIdentityResolver.php:12-19`),
  overwritten by the engine's `AffiliatesIdentityResolver` when installed
  (`packages/affiliates/src/Network/AffiliatesIdentityResolver.php`, merchant row first,
  user-key fallback). Click correlation is the tracked slug in `?anl=` plus the
  `LinkClick` row reachable via subject — no separate click-id plumbing.
- **Offer gate facts** (`src/Models/AffiliateOffer.php:300-317`): active = Published
  status within starts/ends window. Rate/cookie_days/volume tiers are catalog/sync
  data, not redirect enforcement.

## What overlaps

| Concern | `aiarmada/links` | Engine (affiliates) | Network (affiliate-network) |
|---|---|---|---|
| Click capture | Per-hit `LinkClick` row: IP, UA, device/browser/OS, bot flag, referrer, UTM, ad click IDs (`RecordLinkClick`) | Upsert-per-cookie `AffiliateAttribution` row: UTM-ish source/medium/…, hashed IP, fingerprint, expiry (`TrackAffiliateVisit`) | Consumes links: `IncrementNetworkLinkClicks` mirrors human `LinkClicked` onto counters |
| Gates | `LinkGateInterface`, core reasons deactivated/expired/limit_reached (`ResolveLink`) | No gate seam; inline checks (affiliate active, rate-limit, self-referral, fingerprint, fraud allow) | `OfferLinkGate`: link/offer/site/approval reasons |
| Expiry / limits | `expires_at`, `max_clicks` (human only), `deactivated_at`, signed-URL TTL | Signed-URL `aff_exp`, attribution TTL (DB) + cookie TTL (browser) | Tracked-link `expires_at` + offer starts/ends + cookie TTL + order-side attribution window |
| Bot / abuse | UA regex + device-detector; record/count flags | Velocity + fingerprint + IP rate-limit + self-referral + fraud-signal scores; no UA list | Defers to links `is_bot` for counting |
| UTM / params | `utm_defaults` merge, incoming override, `parameters` always win (anti-spoof), 10 ad click IDs | source/medium/campaign/term/content columns + `metadata.utm` snapshot; free `params` on signed URLs | `sub1/2/3` + custom parameters merged into tracked-link `parameters` (which then always win) |
| Events | LinkCreated/Updated/…/Clicked/Blocked/Expired/LimitReached | AffiliateCreated/Activated/Attributed/ConversionRecorded, FraudSignalDetected | OfferCreated/Updated, Application*, NetworkConversionRecorded |
| Cookies | None (stateless redirect) | `affiliate_session` plaintext UUID, DNT + consent options | `affiliate_network_link` encrypted payload, DNT option |
| Counters | total/human + first/last click | `clicks`/`conversions` columns never incremented | clicks/conversions/revenue incremented via listener + conversion action |

## Point-by-point mismatch

### Engine vs `aiarmada/links`

| Need (engine) | `aiarmada/links` offers | Gap |
|---|---|---|
| Signed query-param URL ment for the merchant's own pages (`?aff=&aff_exp=&aff_sig=`) | Cloaked `/go/{slug}` redirect to an arbitrary destination | Opposite mechanics: engine links never redirect through package routes; links rows must redirect |
| Upsert-one-row-per-cookie attribution with TTL refresh (`TrackAffiliateVisit`, `TouchAffiliateAttribution`) | One immutable row per hit (`LinkClick`) | Storage-model mismatch; porting visits to clicks multiplies rows and loses the refresh-extended window |
| Fraud pipeline keyed on affiliate + hashed IP with blocking scores (`FraudDetectionService`, `ClickVelocityRule` + 5 siblings) | UA-regex bot flag + human/total counters | No velocity, fingerprint, self-referral, or signal persistence; blocking semantics differ (score threshold vs hard gate) |
| Commission engine: program rules, voucher overrides, multi-touch weights, upline, idempotent conversions (`RecordAffiliateConversion`) | No conversion concept; third parties listen to `LinkClicked` | The entire money side would still be hand-rolled; links contributes nothing |
| `?aff=` links already in the wild + cookie middleware + voucher/cart bridges reading the same cookie | Global-slug namespace + stateless redirects | Migration breaks every issued URL and every middleware/bridge contract |
| Dependency-light (`contacting`, `commerce-support` only) | Pulls `laravel-actions` + `device-detector` | Dead weight for a package that never renders device reports |

### Network vs `aiarmada/links`

| Need (network) | `aiarmada/links` offers | Gap |
|---|---|---|
| Backing redirect + slug + signed URL + click row | Exactly that (`OfferLinkService::createTrackedLink`) | None — already adopted |
| Offer/site/approval policy on redirect | Gate seam (`LinkGateInterface`) | None — `OfferLinkGate` fills it |
| Human-only network counters | `LinkClicked` with `is_bot` flag | None — `IncrementNetworkLinkClicks` filters |
| Encrypted attribution cookie + order/postback conversions + ledger | Nothing (stateless, no conversions) | Correctly stays network-owned; links never claimed it |

## Cost of forcing it

**Engine (do not):**

- Rewrite `AffiliateLinkGenerator`'s signed-URL scheme into redirect rows, invalidating
  every `?aff=` URL in the wild and the `TrackAffiliateCookie` /
  `CapturePublicAffiliateReferral` / voucher / cart-bridge readers built around it.
- Convert the one-row-per-cookie attribution model (with TTL refresh and
  fingerprint-mismatch rotation) into one-row-per-hit clicks, exploding row volume and
  reimplementing window semantics links doesn't have.
- Re-key the six fraud rules from affiliate+hashed-IP to link slugs, or run both
  systems and reconcile bot-vs-fraud verdicts that answer different questions.
- Fork the links tables with affiliate columns (affiliate_id, program_id, commission
  state) or maintain parallel rows plus sync — at which point it is the current
  hand-rolled model with extra steps and two sources of truth.
- Pull `laravel-actions` + `device-detector` into the engine for device reports the
  engine never renders.

**Network (nothing to force):** the integration already exists and follows the seams
links designed for (subject morph, gate binding, `LinkClicked` listener). "Replacing"
would mean deleting `OfferLinkGate`, `IncrementNetworkLinkClicks`, and the
`createTrackedLink` half of `OfferLinkService`, then re-adding them.

## Is `aiarmada/links` useful anywhere in the affiliate story?

- **Network: yes — it already is the redirect layer.** Keep `OfferLinkService` /
  `OfferLinkGate` / `IncrementNetworkLinkClicks` as-is. Nothing to build.
- **Engine creator share links: plausible, narrow, additive-only.** `AffiliateLink`
  already has `short_url`, `custom_slug`, and `tracking_domain` columns
  (`src/Models/AffiliateLink.php`) with no redirect implementation behind them, and its
  `clicks` counter is never incremented. Minting an optional backing `Link`
  (subject = the `AffiliateLink` morph, gate = affiliate-active/program-membership
  check, listener mapping `LinkClicked` → `TrackAffiliateVisit` / `incrementClicks`)
  would give creators cloaked `/go/` URLs without touching attribution, fraud, or
  conversions. That mirrors the network's proven split: links owns the redirect, the
  affiliate side owns the money facts.
- **Engine public referral route (`/r/{code}`):** same optional shape, but the current
  `CapturePublicAffiliateReferral` already captures + cookies + redirects correctly.
  Only revisit if cloaked slugs or per-link expiry become requirements.
- **Not useful for:** replacing `TrackAffiliateVisit` / `AffiliateAttribution`,
  replacing any fraud rule, replacing `RecordAffiliateConversion` or the commission /
  upline / payout pipeline, or replacing either cookie design (links has no cookies by
  design).

## Recommendation

Keep both affiliate implementations hand-rolled where they are. The network's use of
links is the reference integration — subject morph, gate binding, event listener — and
needs no change. The engine should stay on signed URLs + cookie attribution + its fraud
and commission pipeline; if cloaked creator share links become a requirement, add links
as an optional redirect front-end behind a new gate and listener, reusing the network's
pattern, without moving attribution or money facts out of the engine.
