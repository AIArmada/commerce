# CHIP Collect Spec-Conformance Review — CLOSED (2026-09-28)

Cross-checked `packages/chip` (client, builder, enums, validation, docs)
against the live official spec: `https://docs.chip-in.asia/openapi/chip-collect.yaml`
(OpenAPI 3.1, 184KB, fetched 2026-09-28) plus the Collect intro and conventions pages.

Status: CLOSED — Batches A–E all fixed and dual-APPROVEd (Codex
`gpt-6-luna` max + OpenCode `space-bunny` max); program complete.
See the Batch E close-out at the end of this file. (B3: the earlier
"3 open product decisions / docs-audit close-out" pointer had no
resolvable target anywhere under `audits/` and was cut.)

## Revision note (same day): SDK + prose cross-check

Three-way review against the official PHP SDK
(`https://github.com/CHIPAsia/chip-php-sdk`, cloned 2026-09-28, v2.1.0)
and CHIP prose docs (`errors`, `direct-post/*`, `callbacks`, `changelog`,
fetched as `.md` 2026-09-28). Reviewers: local pass + Codex `gpt-6-luna`
(max) + OpenCode `space-bunny-free` (max), all from the same unanchored
brief; every cited claim re-verified against primary sources. Panel
transcripts are uncommitted (`/tmp/panel-chip/`). Items below marked
REVISED/CONFIRMED/NEW reflect the converged verdict; original item
numbers are kept where they still apply.

Convergence round (same day): each voice's unique additions were sent to
the other model for verification (3 questions per panel, one per prompt,
low effort; transcripts `conv-*-out.txt`). All six answered: Codex
confirmed N15 (+ documented CHIP backfill), both EWallet halves (incl. a
live-guide re-check), and the `error_code` gap with a handler
qualification; bunny retracted its XOR CONFIRMED to half-enforced,
confirmed the refund-union handling with two caveats, and split the
client-data verdict (typed path complete; checkout hand-rolls its own).
Refinements are folded into bugs 8, 9, 11, 13, the refund note, and the
coverage gaps below.

## Verified correct

- 31 of 33 spec operations covered with correct methods and trailing
  slashes (REVISED from "All 33": `PATCH /webhooks/{id}/` and
  `POST /company_statements/` are missing — see bug 7. SDK implements
  both: `WebhooksResource::partialUpdate`, `StatementsResource::schedule`).
- Purchases create + retrieve + 8 actions (`cancel`, `refund`, `charge`,
  `capture`, `release`, `mark_as_paid`, `resend_invoice`,
  `delete_recurring_token`) — REVISED from "CRUD": the spec has no
  purchase list/update/delete. Local and SDK both correctly omit them.
- `Authorization: Bearer <secret_key>` on Collect calls.
- Prices in minor units; quantity stringified; per-product `discount`
  and `tax_percent` conditional on the fluent path only (see bug 5 for
  the object path).
- Client / `client_id` half-enforced (REVISED from "XOR enforced"):
  neither-case and email-required are validated (`PurchasesApi`
  ~L767-776), but BOTH set at once is accepted — spec prose allows only
  one. `clientId()` unsets `client`, but a later `customer()` re-adds it
  without clearing `client_id` (see bug 11).
- All 27 spec webhook events in `WebhookEventType` (REVISED from 26 —
  count erratum only, coverage was already complete); 21/21 purchase
  statuses mapped.
- Webhook verification matches spec exactly: raw body buffer, base64,
  RSA PKCS#1 v1.5 + SHA256 over `X-Signature`, with split keys
  (company-wide `GET /public_key/` for success callbacks,
  `Webhook.public_key` for webhooks). Nuance: the webhook key is picked
  by trying configured candidates, not inferred per-request.
- `skip_capture`/preauth, `force_recurring`, 3 redirect URLs, `due`/`due_strict`,
  `send_receipt`, `reference`, `notes`, `metadata`.
- `mark_as_paid(paid_on)`, `refund(amount)`, `charge(token)`,
  `capture(amount)` params match spec bodies. Refund handles the
  Payment-or-Purchase(`pending_refund`) union; the SDK always returns
  Purchase (local is more correct here).
- `?preferred=` checkout deep-linking is spec'd; `?active=` pre-select
  alternative exists in spec + prose. `FpxType`/`FpxBank` match the prose
  tables exactly (incl. `MBSB001`). `fpx_bank_code` and `razer_bank_code`
  are prose-real (absent from OpenAPI only) — keep, always on
  `checkout_url`.
- `getPaymentMethods` defaults `brand_id`/`currency`/`amount=1000` per
  spec guidance (prose: RM 10 safe since 2026-07, Atome minimum) —
  better than the SDK's own amount-less example.

## Q2 (from docs-audit close-out) — ANSWERED, with scope notes

`tax_percent` = percent of tax ADDED to the product price, computed
server-side ("computed" is inference from request/response behavior, not
a quoted guarantee). `subtotal_override`, `total_tax_override`, and
`total_discount_override` are explicitly **visual-only** ("setting it won't
impact `total`"); only `total_override` changes the charge *among the three
aggregate display overrides*. Two separate total-affecting inputs sit
outside that statement: `debt` (added/subtracted to invoice total) and
per-product `total_price_override`. Our `total_override` mapping is
correct and `fromCheckoutable()` (always sets a reconciled
`total_override`) has no overcharge path — but bare `.discount()`
callers do (see bug 4).

## Bugs (fix on revisit)

1. **Double tax at line level** — `CartCheckoutLineItem` passes item
   `tax_percent` through while the item price already includes tax
   conditions (`MoneyTrait::getRawPrice()` applies ALL item conditions).
   CHIP adds the percent a second time. Charge is safe (`total_override`
   governs); invoice line display inflates. Fix: report `0.0` in the wrapper.
   - `packages/cart/src/Models/CartCheckoutLineItem.php`
   - Note: fix direction mirrors the existing discount-zeroing; the
     invoice-render effect still needs a sandbox purchase to confirm.
2. **`purchase.total` sent but absent from spec** — `fromCheckoutable()`
   sets `$data['purchase']['total']`; `PurchaseDetailsBody` has no `total`
   field. Strip it; keep `total_override`. Fix must also update the local
   readers/validators of `total`.
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~line 255)
   - `packages/chip/src/Services/Collect/PurchasesApi.php` (~lines 91, 827-836)
   - Note: SDK declares `total` as a (response/writable) model field, and
     the server's unknown-field policy is unverified — sandbox check.
3. **REVISED — wire already clean; real gap is the `Idempotency-Key` header.**
   The builder stages `idempotency_key`, but `PurchasesApi::create`
   strips it before the single `POST purchases/` call site, and a test
   pins the stripped body. Remaining cleanup is builder-level only.
   The real finding: CHIP documents an `Idempotency-Key` **header** for
   write ops (`errors.md`, absent from OpenAPI); neither local nor SDK
   sends it, so crash-window protection is purely local. Adopt the header.
   - `packages/chip/src/Services/Collect/PurchasesApi.php` (~lines 58, 397-434)
   - `tests/src/Chip/Unit/Services/Collect/PurchasesApiTest.php` (~lines 197-218)
4. **`discount()` is display-only** — sets `total_discount_override`, which
   per spec won't impact `total`. Worse than first stated: zero callers,
   in no doc file, and its docblock ("reduces the total amount charged")
   is factually wrong. Fix docblock + usage docs loudly.
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~line 564)
5. **`ProductData::toArray()` emits `"category": null`** — spec types
   `category` as non-nullable `string`. Wider than first stated: the same
   `toArray()` feeds the live `createCheckoutPurchase()` path (which also
   always sends `discount: 0` and `tax_percent: 0.0`), and three test
   files pin `'category' => null` — fix must update fixtures. The 400
   risk is likely theoretical (checkout works today); omit-nulls still
   matches the SDK.
   - `packages/chip/src/Data/ProductData.php` (~line 152)
   - `packages/chip/src/Services/Collect/PurchasesApi.php` (~lines 658-661)
   - `tests/src/Chip/Unit/Data/ProductDataObjectTest.php:34`,
     `ProductDataObjectMoneyTest.php:73`, `PurchaseDetailsDataObjectTest.php:66`
6. **REVISED — FPX docs: heading is CHIP's own term; URL is the defect.**
   "FPX Direct Post" matches CHIP's FPX prose usage (not a mislabel), and
   `fpx_bank_code` is verified real per the prose bank-code tables. The
   remaining defect: the example appends `?preferred=` to `direct_post_url`
   when present — always build on `checkout_url` (`/p/{purchase_id}/`).
   Add `?active=` as the non-skipping alternative.
   - `packages/chip/docs/07-chip-collect.md` (~line 68)
7. **NEW — two spec operations unimplemented (31/33).** Add
   `PATCH /webhooks/{id}/` (partial update) and
   `POST /company_statements/` (schedule); SDK has both. The local
   `CompanyStatementData` already carries spec-shaped `format`
   (`csv`/`xlsx`) + `timezone`, so the schedule fix is the POST call only.
   - `packages/chip/src/Services/Collect/WebhooksApi.php`
   - `packages/chip/src/Services/Collect/AccountApi.php`
8. **NEW — `fromCustomer()` drops the client it just built.** When the
   customer has a gateway ID, the trailing `clientId()` call unsets the
   whole `client` payload (email/name/phone/addresses silently lost).
   Cross-verified: Codex confirmed the drop and quotes the spec's
   `client_id` prose ("All `ClientDetails` fields from the Client will be
   copied"), so charges survive on backfilled details while fresh
   checkout data is silently lost. Reachable via `ChipGateway`.
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~lines 285-322)
   - `packages/chip/src/Gateways/ChipGateway.php` (~line 72)
9. **NEW — `EWallet` diverges from prose.** ShopeePay must be
   `?preferred=shopee_pay` with NO `razer_bank_code`; local sends
   `preferred=razer_shopeepay` + code. Atome (`razer_atome`/Atome)
   missing entirely. Cross-verified: Codex confirmed both halves
   against the live guide.
   - `packages/chip/src/Enums/EWallet.php`
10. **NEW — turnover docs examples are invalid.** `07` uses
    `start_date`/`end_date` (not spec params); `04-usage` uses ISO date
    strings for `from`/`to`. Spec wants `from`/`to` unix timestamps plus
    exactly one `currency`; the client forwards filters unchanged, so the
    examples as written fail.
    - `packages/chip/docs/07-chip-collect.md` (~lines 209-213)
    - `packages/chip/docs/04-usage.md` (~lines 195-198)
11. **NEW — client + `client_id` both-case accepted.** Validator rejects
    neither-set but not both-set; builder `customer()` after `clientId()`
    re-adds `client` without clearing `client_id`. Spec prose allows one
    only. (Spec is self-inconsistent here: `PurchaseBody.required` lists
    `client` unconditionally.) Properly converged: bunny retracted its
    original CONFIRMED to half-enforced when shown the missing guard.
    - `packages/chip/src/Services/Collect/PurchasesApi.php` (~lines 767-776)
    - `packages/chip/src/Builders/PurchaseBuilder.php` (~lines 337-369)
12. **NEW — error parsing misses CHIP's `__all__` shapes.** Spec shows
    `__all__` as object (generic 400) and array (charge errors); local
    reads top-level `message`/`error` + `errorData['code']`, so real 400s
    (e.g. `invalid_recurring_token`) come back with null code and a
    generic message. The prose taxonomy (403/409/429 + `Retry-After`,
    ...) collapses into one `ChipApiException`. (SDK misses them too.)
    - `packages/chip/src/Clients/ChipCollectClient.php` (~line 129)
    - `packages/chip/src/Exceptions/ChipApiException.php` (~line 59)
13. **NEW — webhook `error_code` never read.** Six values documented in
    `errors.md` on `purchase.payment_failure` + `purchase.cancelled`;
    the literal field is referenced only by the test factory. Qualified
    on cross-check: `PaymentFailedHandler` already persists failure detail
    from `transaction_data.attempts[*].error`, so not all failure signal
    is lost — wire the documented `error_code` in as the structured
    reason there (and decide whether `PurchaseCancelledHandler` should
    persist a reason at all).
    - `packages/chip/src/Webhooks/Handlers/PaymentFailedHandler.php`
    - `packages/chip/src/Data/EnrichedWebhookPayload.php`

## Docs gaps (improvements, not bugs)

- `09-webhooks.md`: no retry-protocol specifics — spec says 8 additional
  attempts, 36h window, at-least-once duplicates possible, sequential per
  source object.
- No test-card docs — spec: `4444 3333 2222 1111` (non-3DS) and
  `5555 5555 5555 4444` (3DS), CVC `123`, any name; anything else fails
  in test mode. Note: spec's own expiry wording conflicts (≥ current
  month vs > now) — sandbox settles it.
- No `?active=` param mention (spec'd pre-select alternative to `?preferred=`).
- NEW: `Idempotency-Key` header; error taxonomy + `__all__` shapes;
  `remember_card=on` token-enrolment step; card Direct Post
  prerequisites/field contract; `debt` semantics; `payment_methods`
  amount rationale (RM 10 / Atome minimum).

## Coverage gaps (not bugs — no builder support unless noted)

- CLOSED (Batch E): fluent-builder gaps — `paymentMethodWhitelist()`
  setter (E1), client `cc`/`bcc`/legal/brand/registration/tax/bank
  setters (E1), checkout billing-address mapping (E3).
  (`ClientDetailsData` serializes all 22 spec fields;
  `Subscription.php` upcoming-invoice preview is display-only —
  both checked, never gaps.)
- Refund-union caveats (handling confirmed correct, all callers branch
  the union): the `type` discriminator is undocumented in the spec's
  `Payment` schema (present in our webhook fixture; live refund-response
  shape unverified) — consider keying on the `payment` key instead; and
  the cashier fake has no `pending_refund` branch, so tests never cover
  the slow path.
- CLOSED (Batch D + E): `WebhookBody.all_events`/`public_key` inputs
  (D4 docs, verbatim passthrough); `tags`, `language`, `debt`,
  `timezone`, `issued`, `email_message`, `request_client_details`,
  per-product `total_price_override` (E1 setters);
  `balance()` filters (E2); validated bounds (E4: `tax_percent`
  0–100, `notes` 10k, `creator_agent` 32, `platform` enum,
  256-char name/category, client lengths, cc/bcc 254).
- `tax_percent` sent as JSON number vs spec'd string type — tolerance
  UNVERIFIED (spec example + SDK send strings; sandbox check).
- Quantity int-only vs spec `type: string` (spec example: `"1.0000"`) —
  fractional quantities rejected client-side; needs a sandbox/prose
  ruling before any change.
- SDK-only scope (in neither spec nor package; not gaps): Billing
  Templates family, card-form helper.

## SDK-vs-spec contradictions (do NOT "align" to the SDK)

- SDK `publicKey->get()` expects a `{"public_key": ...}` object, but the
  spec returns a JSON-encoded PEM string — against spec-shaped responses
  the SDK returns `''`, and its test pins the wrong shape. Local string
  handling is correct per spec.
- SDK examples verify webhooks with the company-wide key; spec requires
  the per-webhook key.
- SDK retries POST mutations on 5xx/429 with no idempotency (possible
  double-mutate); local GET-only retry policy is deliberately safer.
  Minor local omission: no 429/`Retry-After` handling.
- SDK refund always returns Purchase; spec declares a Payment-or-Purchase
  union (local handles it).
- SDK money epsilon-coercion vs local throw-on-noise: both safe (never a
  wrong amount), SDK more forgiving (`"108.00"`, float noise).

## Residual external unknowns (all need sandbox or support)

- How CHIP's server combines conditioned line prices with `tax_percent` for
  invoice display ("added to the price" only) — original unknown, unchanged.
- Unknown-field policy: is `purchase.total` in a create body harmful or
  ignored? Is `total` writeable at all?
- Is numeric `tax_percent` accepted? Does `"category": null` 400?
  (Includes exotic-but-legal numeric strings like `'1e2'`, which the
  local rule accepts and emits as given.)
  Is `quantity: "1.5000"` accepted?
- Is the `Idempotency-Key` header honored? (Same header, different
  reference, count resulting purchases.)
- Are the Billing routes live? (Probe `GET billing_templates/`.)
- Does `direct_post_url` accept `?preferred=`/`fpx_bank_code`?
- Are 403/409/422 actually emitted? Is current-month card expiry accepted?
- Spec inconsistencies to raise with support: `PurchaseBody.required`
  vs client XOR prose; `__all__` object vs array; `PublicKey` string vs
  the SDK's object shape.
- Webhook `all_events: true` alongside a populated `events` on create:
  accepted? (Schema `required` lists `events` unconditionally while
  prose says either/or; SDK fixtures carry both, leaning accept.
  Batch D docs include both until CHIP clarifies.)

## Batch A audit deferrals

- F11 (deferred to Batch D): `### Product` block in
  `packages/chip/docs/10-api-reference.md` documenting
  `ProductData::toArray()` vs `toRequestArray()`; lands with D4.

## Batch B close-out (dual-APPROVEd, 5 confirm rounds)

- B1–B8 plus review rounds C/D/E: condition-scoped line-tax zeroing,
  Retry-After delay-seconds + strict 3-format HTTP-date (incl. RFC 9110
  §5.6.7 full-timestamp 50-year rule), masked single-warning 429 logging
  on both clients, `__all__` object/list extraction (redundant guard
  removed, coercion kept), and attribute-tax-inclusive cart totals.
- Verification at close: Chip 817 passed, Cart 1000 passed + 2 skipped,
  Pint PASS, PHPStan level 6 clean, every hunk mutation-killed with
  byte-identical restores.
- Weekday-shift mechanism CONFIRMED by probe (was hypothesis): a wrong
  weekday name advances the date to the named weekday
  (`Mon, 30 Jun 2075` → 2075-07-01, no parse warnings). Shift is bounded
  to ≤6 days; still deferred (Batch C ticket).
- Batch C manifest proposed for next round: v3 C1 (missing webhook/company
  statement operations) + C2 (idempotency header, 13 caller dispositions)
  plus accrued tickets — JSON-scalar `TypeError`, non-429 double error
  log, unlogged local limiter, weekday leniency, `Retry-After` clamp
  (DEFERRED agreed: re-open on a published upstream maximum or an
  observed bad value; digit-path-only clamp never ships alone),
  C-5 chip docs (`__all__`, HTTP-date form, 50-year rule, 0-clamp,
  60 default; incl. `ChipApiException.php:55` docblock fix by path),
  `$candidate === false` branch test, leap-day boundary note, `+1d`
  companion comment.

## Batch C close-out (dual-APPROVEd, 2 review rounds)

- C1 (webhook PATCH, company-statement schedule/cancel + service
  validation) + C2 (conditional `Idempotency-Key` on create/mutations,
  8 widened actions + bridges + facade, leaf/reference/monthly/invoice
  dispositions) + tickets T1/T2/T3/T4/T7/T8/T9 (D-e bundled).
- Two manifest prerequisites discovered at implementation, both
  panel-ruled: currency forwarding (`withCurrency`, no invented
  default — subscription leaves were broken end-to-end) and the T4
  two-sided RFC 850 rule (dedicated path, single clock read,
  tokenless boundary; token may evidence either century).
- R1: Codex reviewability-BLOCK (no semantic dispute) → evidence
  pack; Bunny 5 blockers (B1/B2 orphan docblocks, S1 stale `@param`,
  S2 fake overrides, D1 doc bullet) + T2 layer-scope question. R2:
  all fixed; T2 narrowed to client layer by decision (extending to
  shared `attempt()` plumbing = scope creep); dual-APPROVE.
- Records: 12 `?string $idempotencyKey` lines = create (Batch A) + 3
  helpers + 8 actions (9-vs-8 reconciled); mutation counts are
  per-file run-observed with cross-file static fan-out noted
  separately; scripted count corrected to 11/11 (M1–M11).
- Verification at close: Chip 844 passed, CashierChip 535 passed
  (Cart untouched — Batch B's 1000 + 2 skipped stands), Pint PASS,
  PHPStan level 6 clean, 11/11 mutations killed with md5-verified
  restores.
- Next: Batch D (incl. F11 `### Product` block + D4 docs carrying
  the deferred C-5 chip-docs ticket), then Batch E (both closed below).

## Batch D close-out (dual-APPROVEd, 3 audit rounds)

- D1 (`discount()` display-only docblock + loud WARNING bullet),
  D2/D2b (FPX + e-wallet `checkout_url` bases, `?active=`
  alternatives, card-form pointer), D3 (three unix+currency
  examples), D3b (widget `from`/`to`/`currency` +
  `incoming.turnover`/`fee_sell` + catch-path reset), D4 prose
  (card payments, test cards, payment amounts, Errors,
  `all_events`, PEM raw usage, retry protocol, webhook
  registration) + api-reference (F11 `### Product`, `->discount()`
  line, C-5 convention bullets).
- Agreement took R1 + R2 deltas (a)-(f): D3b response-keys
  expansion, D2b twin, D4 api-reference correction (C1/A4-C2
  already landed), debt split (response-side in D, request-side
  with E1), third turnover example (coordinator's Batch C bug,
  owned), D1 blast-radius widening.
- Audit R1 split (Codex APPROVE, Bunny BLOCK B1–B4); R2 fixed all
  four (webhook `title`/`callback`, card-form `cvc`+submit, live
  error precedence, invariant reword) plus N1 (schedule `currency`
  required — prior rationale wrong, owned) and N5 (≤0 amounts);
  R3 converged B1v2 on Codex (include `events` with `all_events`
  — OR-prose means at-least-one, so both satisfies both readings)
  and folded two precision nits (Send-400 merge scope, trim
  acknowledgment). Third nit (subscription keys in L107) and the
  under-delivery-hint suggestion deliberately declined with
  rationale (placement correct; speculation without evidence).
- Discovery ruled by both panels: D3b catch-path off-by-one
  (31-vs-30 labels) fixed with a 3-line reset, pinned by a
  mid-loop-failure test.
- Verification at close: Chip 844 passed (unchanged),
  FilamentChip 34 passed (31 + 3 new), Pint PASS, PHPStan level 6
  clean (chip + filament-chip), 3/3 widget mutations killed with
  verified restores. CashierChip 535 / Cart 1000+2skip stand
  (untouched by this batch).
- Next: Batch E (closed below).

## Batch E close-out (dual-APPROVEd, 3 audit rounds)

- E1 (12-case `RequestClientDetail` enum, 16-value whitelist
  const, 16 setters with exact nesting, per-product
  `total_price_override`, `ProductData` property/serializers),
  E2 (filtered `balance()` chain + fakes), E3 (billing-address
  mapping, `country_code`-only), E4 (shared bounds matrix +
  negative-tax throw, no-op injection assertion DELETED per A7),
  E5 (as-given string tax, `from()` cast kept), E6 (API-site
  quantity pin, no change), E-docs (api-reference, 07, checkout
  06, whitelist-divergence note).
- Agreement took R1 + R2 amendments A1–A10 (all verified):
  `issued` string (A1), six client lengths + 254 email cap
  (A2/A3), empty-whitelist-throw/RCD-[]-valid (A4), E3 trim
  (A5), fingerprint-window accept-and-record (A6→b),
  assertion deletion (A7), `from()` cast kept (A8), single-pass
  tax rule (A9), API quantity test (A10).
- Audit R1 split: Bunny BLOCK (B1 stale `debt` sentence, B2
  missing close-out/strikes — both fixed in-round) with both
  refinements upheld (E3 evidence strengthened: shipped-default
  transformer makes the fallback reachable); Codex BLOCK (one
  new issue: `getTotalPrice()` ignored the override — fixed at
  the single accessor, cents + collection inherit). N1 folded
  (doc `= null` defaults); N2/N3/N4 ticketed below; N5 folded
  into the numeric-tax residual.
- Verification at close: Chip 871 passed (2517 assertions,
  re-confirmed on a second consecutive full run post-TPO-fix),
  Checkout 262 passed, CashierChip 535 passed (Cart 1000+2skip
  and FilamentChip 34 stand untouched), Pint PASS, PHPStan
  level 6 clean (chip + checkout), 6/6 E-mutations killed
  with verified restores.
- Follow-ups (non-blocking): N2 trim cc/bcc on emit;
  N3 cap E3 address fields (128/128/128/32); N4 TPO vs
  `subtotal_override` reconciliation (unreachable today).
- Audit R2: Codex APPROVE (all six fixes accepted, no new
  disputes — description-based, no tree re-inspection,
  disclosed in verdict); Bunny confirmed all six fixes CLOSED
  with file:line evidence (incl. N4 reachability and the
  `'1e2'` pass-through proof) but BLOCKed on B3: this file's
  own header still read OPEN / "C–E pending", contradicting
  the close-out — fixed in-round (header → CLOSED, status
  rewritten, dangling "docs-audit" pointer cut after a
  repo-wide search found no target, Batch C "Next" swept),
  satisfying Bunny's conditional approve. Docs-only delta;
  no suite re-run attributable to B3.
- Program complete: Batches A–E all dual-approved. Remaining
  work is sandbox/support probes (residual unknowns) and the
  merge of `fix/chip-collect-spec-review-2026-09-28`.

## Addendum 2026-09-29: N2/N3/N4 tickets + sandbox probes

Tickets implemented solo, reviewed by Codex (`gpt-6-luna` max) only.
- N2: `normalizeEmailList()` trims on emit (`PurchaseBuilder.php`).
- N3: 8 address caps at the API layer (street/city/state 128,
  zip 32, billing + shipping). `country`/`shipping_country`
  deliberately NOT capped — see probe P10 below.
- N4: subtotal honors per-product `total_price_override` (as-is,
  not x quantity; null = absent) and rejects negatives — the
  negative throw matches the server's live `min_value` 400 (P11).
- Verification: 14 new tests (12 ran red first), Chip suite 885
  passed, Pint clean (incl. 2 pre-existing `concat_space` lines),
  PHPStan L6 clean.
- Codex R1 BLOCKed on F1 (add 2-char country caps) and F2
  (negative-TPO throw vs schema silence); both resolved against
  the live probes below — caps declined, throw kept. Codex R2
  APPROVEd with two non-blocking evidence notes (P4c artifact
  gap, `shipping_country` inferred), both closed with fresh
  probes in-round — approval stands, no R3 needed.

Sandbox probes (2026-09-29, `is_test=True`, script at
`/tmp/panel-chip/probe-chip.sh`, bodies under
`/tmp/panel-chip/probes/` — creds from `~/Herd/unfair/.env`,
sandbox environment, never committed):
- P1 unknown-field `purchase.total: 999999` → 201, silently
  ignored (calculated 100 kept and stored). `total` not writable.
- P2a numeric `tax_percent: 6` → 201, echoed `"6.00"`.
- P2b `tax_percent: "1e2"` → 201, parsed as 100 (echoed
  `"100.00"`, total doubled). As-given emit is safe.
- P2c `category: null` → 400 `"may not be null"`. `toRequestArray`
  strips nulls, so only hand-built raw payloads can hit this.
- P3 `quantity: "1.5000"` → 201, honored (total 1500, echoed
  as given). Server accepts fractional quantities; the client
  still rejects them — needs a rounding-policy decision before
  any client change (open ticket T2).
- P4 `Idempotency-Key` replay (same key, then identical
  payload) → two distinct purchases (201 + 201). Header NOT
  honored; the local ledger is the only dedupe. Docs updated.
  Re-proven with full artifacts after Codex R2 (`p4redo.body.json`
  + `p4redo.key.txt` + `p4redo-a/c.json`): same body, same key,
  distinct ids.
- P5 `GET billing_templates/` → 200, empty list. Routes live
  (SDK-only scope; future package surface, no action).
- P7 `{}` → 400 per-field `[{message, code}]` arrays; only
  `purchase` + `brand_id` flagged — `client`/`client_id`
  absent without complaint (leans toward client truly
  optional server-side; not conclusive).
- P8 webhook `all_events: true` + `events: [...]` → 201
  accepted (probe webhook deleted, 204).
- P9 price 10000 + `tax_percent: "6"` → total 10600 ("added
  to the price" confirmed). GET responses carry no
  subtotal/tax breakdown (only overrides + total).
- P10 `country: "Malaysia"` → 201, stored `"MY"`;
  `"United States of America"` → 201, stored `"US"`;
  200-char garbage → 400 `invalid_choice`. Country is a
  server-side choice field, not maxLength-2 — hence NO
  local cap (Codex F1 declined with evidence). Codes
  preferred; checkout path sends code-or-nothing.
  `shipping_country` directly probed after Codex R2
  (`p10b.json`): full name → 201, stored `"US"`.
- P11 `total_price_override: -100` → 400 `min_value`
  ("greater than or equal to 0"). N4's local throw is
  server-parity, not just policy (Codex F2 declined).
- P6 `direct_post_url` params: UNPROBED (field `None` on
  plain purchases; only set for card flows).

Still open (support questions, not probeable): 403/409
emission shapes, current-month card expiry acceptance,
`direct_post_url` param behavior, spec inconsistencies
(`PurchaseBody.required` vs client XOR prose — P7 leans
optional; `__all__` object vs array; `PublicKey` string
vs SDK object).

## Addendum 2026-09-29 (cont.): T2 fractional quantities + P12–P15

- T2 implemented: quantities accept finite numerics > 0
  below 2^53 with at most 4 decimal places (int,
  whole/fractional float, numeric string incl. `"1.5000"`,
  `"1e2"`, `" 2 "`, `"+2"` — all sandbox-accepted; 5dp+
  is a server 400 `max_decimal_places`, mirrored locally
  by string-counting decimals (float casts would collapse
  forms like `"1.0000000000000001"`); >= 2^53 rejected as
  out-of-range since float64 money math cannot represent
  it exactly). Both `normalizeQuantity` sites
  (builder + API) widened to `int|string`, emitting
  as-given; both subtotal sites use the shared
  `ProductData::multiplyMinorUnits` half-up primitive
  (widened private → public, zero duplication). This also
  unblocks fractional `LineItemInterface` quantities
  (contract already `int|float`). 10 new/rewritten tests
  (all red first), incl. a 312-total tie-break mirror.
- P12 fractional cents: 100 x 1.555 + 100 x 0.5 → total
  206 (half-up consistent). P13 tie-break: two 100 x 1.555
  lines → 312, proving PER-LINE half-up (total-rounding
  would give 311). P14 zero quantity → 400 (rejected via the
  preauthorization guard, not a dedicated quantity rule — but
  rejected, so the local `> 0` rule is safe). P15 bad key → 401
  `{"__all__":[{"message":"Incorrect secret_key",
  "code":"authentication_failed"}]}` — auth failures are
  401, and `__all__` is an ARRAY in every observed case
  (401, 405, zero-qty 400).
- Verification: builder/interface/API/Data files green
  (38 + 14 + 65 + 93), full Chip suite 893 passed
  (2583 assertions), Pint PASS (incl. mb_* sweep of the
  new helper), PHPStan L6 clean.
- Luna T2-R2 APPROVEd (BLOCK refuted with the override
  arithmetic worked through; no Σ-discount scope needed).
  Its two non-blocking precision notes were both fixed in
  kind: decimals string-counted per decimal-field rules,
  bound tightened 2^63 → 2^53 — micro-confirm R3
  dispatched on that delta.
- P16 no-client create → 400 `purchase_client_or_id_required`
  (`__all__` array): client XOR client_id IS required
  server-side — answers draft Q4 (dropped from the ticket).
- P17 price 100 / discount 1 / qty 1.5 → total 149: server
  rounds NET per line, matching `ProductData::getTotalPrice`
  exactly. The ±1 gap vs subtotal-minus-discount-total is
  inherent half-up parts behavior, documented on the
  accessor; no local check mixes the pair.
- P18 quantity forms: `"1e2"`, `" 2 "`, `"+2"` accepted;
  `"1.55555"` → 400 `max_decimal_places`. Local 4dp rule
  mirrors the server; float dust below 14 significant
  digits stringifies away before the check (validates the
  wire form).
- Luna T2-R1 BLOCKed on discount-rounding parity; rebutted
  with P17 (server == our net accessor; response check
  compares server-total vs caller-override, neither side
  from the divergent pair; mixing our accessors to pin
  overrides throws locally first). Its three non-blocking
  notes all produced real fixes: 4dp bound (P18), 2^63
  range bound, draft rewording (Q4 dropped, Q5/Q2/Q3
  qualified as tested-vs-untested). Card-flow probe for
  `direct_post_url` skipped: needs test-card tokenization
  through the card form, disproportionate to a support
  question — recorded as untested, not untestable.

Support ticket draft (send as-is; evidence from sandbox
probes P1–P23, brand in `~/Herd/unfair/.env`):

> Subject: Collect API — status codes, error envelope,
> and two doc conflicts
>
> Hi CHIP team — we're integrating against Collect
> (sandbox-verified; ~40 probed calls, all `is_test`).
> Four items we could not settle:
>
> 1. Status codes: your errors guide lists 403
>    `forbidden`, 409 `conflict`, and 422
>    `unprocessable_entity`, but across ~40 calls we saw
>    only 400, 401, 404, and 405. The 400s cover
>    validation plus state violations (a repeat cancel,
>    and release/refund/capture on a fresh purchase,
>    return 400 `purchase_*_wrong_status` — the first
>    cancel succeeds; tokenless charge returns 400
>    `invalid_recurring_token`). Your guide names
>    capture-on-captured as a 409 case (needs a paid
>    purchase) and over-capture as a 422 cause (needs
>    an authorized hold) — our curl probes reached
>    neither prerequisite state: `cardpost.html`
>    returned the JS-gated form, and `cardpaid.json`
>    remained `viewed`. Which operations actually
>    return 403/409/422? (Side note: bad keys return
>    401 `authentication_failed`, not the guide's
>    `unauthorized`.)
> 2. Card-expiry wording conflict: the spec says "any
>    expiry larger or equal to the current month/year"
>    (:88) but also "any date/month greater than now"
>    (:160), while the payment-link and pre-auth docs
>    both say "no earlier than the current month/year".
>    Please confirm a current-month expiry is accepted
>    and align :160. (Our live card attempt was
>    inconclusive: the endpoint returned the JS-gated
>    form and the purchase stayed `viewed` — no expiry
>    signal either way.)
> 3. `__all__` shape: the spec shows an ARRAY at :301
>    and :327 but an OBJECT at :1269. The `__all__`
>    values in all 11 non-field errors we observed were
>    arrays (field errors use keyed/nested shapes
>    instead of this envelope). Is the object form ever
>    emitted, or is :1269 stale?
> 4. Idempotency docs conflict: the errors guide says a
>    repeated body with the same `Idempotency-Key`
>    "will return the same result without performing
>    the action twice", but our sandbox replay
>    (identical body, same key) created a second
>    purchase. Please confirm the header is currently
>    ignored and either honor it or fix the guide.
>
> Settled ourselves, no action needed: client XOR
> `client_id` fully enforced both halves
> (`purchase_client_or_id_required` +
> `purchase_client_or_id_only`); `GET public_key/`
> returns a string (spec canonical — note the official
> PHP SDK cannot handle it: its client types
> `array|stdClass` while the resource expects
> `['public_key']`, so static inspection indicates a
> `TypeError` against live); `?preferred=` /
> `fpx_bank_code` are checkout-URL features (direct-post
> docs) — no rendered body change observed when passed
> to `direct_post_url`.
>
> Thanks!

## Addendum 2026-09-29 (cont. 2): card flow + P19–P21

- P6 CLOSED: `?preferred=`/`fpx_bank_code` are CHECKOUT-URL
  features (direct-post intro docs, official example with
  `MB2U0227`), not `direct_post_url` features — the
  residual's premise was wrong. Live: checkout URL +
  params → 302 to `payments.chip-in.asia` (headers saved
  in `p6b.headers`; params consumed, not echoed); page
  content is JS (curl cannot confirm preselection).
- P19 `GET public_key/` → 200 JSON string (PEM). Spec
  canonical; the official SDK cannot handle it — its
  client types `array|stdClass` (`GuzzleClient.php:45`,
  `json_decode` passthrough) while the resource expects
  `['public_key']` (`PublicKeyResource.php:20`, test
  mocks that shape), so static inspection indicates a
  `TypeError` against live. SDK-side skew, reported in
  the draft FYI. Old Q5 dropped.
- P20 expiry: "no earlier than current month/year" on
  BOTH the payment-link and pre-auth docs pages, plus
  spec :88 — 3:1 for current-month-valid against spec
  :160 ("greater than now"). Live card attempt
  inconclusive (JS-gated form returned, purchase stayed
  `viewed` — no expiry signal either way); kept as
  draft Q2 spec-alignment ask with line cites.
- P21 `direct_post_url`: obtained by adding
  `success_redirect`/`failure_redirect` (spec :3015
  conditions) — GET renders the form (200, brand title);
  with `?preferred=fpx&fpx_bank_code=MB2U0227` no
  rendered body change observed (byte-identical).
  Old Q3 dropped (wrong premise).
- P22 both-supplied client+client_id → 400
  `purchase_client_or_id_only`. With P16, XOR fully
  established live; local both-reject matches (the
  audit's ":50 accepted locally" note was pre-fix
  state). "XOR enforced" restored with complete
  evidence after Luna's challenge.
- P23 state violations: release/refund/capture on a
  fresh purchase → 400 `purchase_*_wrong_status`
  (arrays); first cancel → 200 (`created`→`cancelled`),
  repeat cancel → 400 `purchase_cancel_wrong_status`;
  tokenless charge → 400 `invalid_recurring_token`
  (token check fires first — charge wrong-status
  untested, needs a valid token). No 409 anywhere.
- 403/409/422: still unobserved (~40 calls). The errors
  guide DOES document all three (403 scopes, 409 state,
  422 semantics) plus a 401 `unauthorized` code that
  mismatches live `authentication_failed` — and its
  Idempotency section promises server dedupe,
  contradicted by P4. Kept as draft Q1 (sharpened) +
  new Q4 (idempotency docs conflict).
- `__all__`: the values in 11/11 live non-field errors
  are arrays (verified by artifact inventory, re-confirmed
  by Luna; field errors use keyed/nested shapes, not this
  envelope) vs spec :1269 object — kept as draft Q3
  (sharpened with line cites). The earlier "7/7" was a
  sloppy count, corrected after Luna's audit. Our own
  docs + `extractAllError` docblock claimed "object on
  generic 400s" — corrected to list-observed (parser
  already accepts all three forms; no behavior change).
- Luna probe-R1 BLOCKed on the XOR half-proof and the
  7/7 + envelope wording; both fixed by probe (P22)
  and recount (11/11) plus wording repair. Its four
  non-blocking notes each produced fixes: errors-guide
  reading (Q1/Q4), JS-scope honesty, SDK-shape
  correction, P21/P6 phrasing + headers artifact.
- Luna probe-R2 BLOCKed on three draft misstatements,
  all fixed: charge split out of the wrong-status claim
  (it returns `invalid_recurring_token`; true charge
  wrong-status needs a token), SDK outcome corrected to
  indicated `TypeError` (verified through
  `GuzzleClient.php:45` + `PublicKeyResource.php:20`,
  cite fixed :15→:20), P21 softened to the observed
  wording. Its non-blocking notes confirmed XOR closure,
  11/11, and Q4, refined Q1 toward the guide's 409/422
  examples, and caught our own docs' object-`__all__`
  claim — docblock + both doc lines corrected (parser
  unchanged). Expiry corroboration snapshots saved at
  `/tmp/panel-chip/probes/docs-{payment-link,preauth}.md`
  for auditability. R3 dispatched.
- Luna probe-R3 BLOCKed on two Q1 misstatements, both
  fixed: cancel split into succeeding-first vs rejected
  repeat (P23 likewise completed), and the 409/422
  examples given their distinct states (paid purchase
  vs authorized hold). Its non-blocking notes confirmed
  the TypeError chain airtight (RetryClient rescues
  only `ChipApiException`), all wording checks, the Q4
  quote, and Q2's honest inconclusive (browser attempt
  still possible). R4 dispatched.
- Luna probe-R4 confirmed the cancel split but BLOCKed on
  "neither state drivable via API" — capture IS an API op,
  so the states are reachable via card-flow-then-capture;
  narrowed to "not reached with our curl probes". R5
  dispatched.
- Luna probe-R5 BLOCKed on the parenthetical (a hold is
  authorization, not a completed payment — status enum
  agrees); replaced with Luna's dictated sentence
  verbatim. R6 dispatched.
- Luna probe-R6 BLOCKed: my "verbatim" had dropped the
  evidence clause. Full dictated sentence now applied
  character-for-character. R7 dispatched.
- Luna probe-R7 APPROVEd (evidence clause verified
  verbatim; all prior rounds' findings closed across
  R6+R7). Probe program complete: P1–P23 resolved,
  4-question support draft approved sendable.
