# CHIP Collect Spec-Conformance Review — CLOSED (2026-09-28)

Cross-checked `packages/chip` (client, builder, enums, validation, docs)
against the live official spec: `https://docs.chip-in.asia/openapi/chip-collect.yaml`
(OpenAPI 3.1, 184KB, fetched 2026-09-28) plus the Collect intro and conventions pages.

Status: CLOSED — Batches A–E all fixed and dual-APPROVEd (Codex
`gpt-6-luna` max + OpenCode `space-bunny` max); implementation complete.
See the Batch E close-out below and the 2026-09-30 review at the end. (B3: the earlier
"3 open product decisions / docs-audit close-out" pointer had no
resolvable target anywhere under `audits/` and was cut.)

## Revision note (same day): SDK + prose cross-check

Three-way review against the official PHP SDK
(`https://github.com/CHIPAsia/chip-php-sdk`, cloned 2026-09-28, v2.1.0)
and CHIP prose docs (`errors`, `direct-post/*`, `callbacks`, `changelog`,
fetched as `.md` 2026-09-28). Reviewers: local pass + Codex `gpt-6-luna`
(max) + OpenCode `space-bunny-free` (max), all from the same unanchored
brief; claims cross-checked against primary sources, with later record
corrections preserved below. Panel
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

## Evidence and chronology (updated 2026-09-30)

The opening review through "Batch A audit deferrals" is the **historical,
pre-fix assessment**, not a list of current defects. Its approximate source
line pointers refer to that tree. Bugs 1–13 were dispositioned in Batches
A–E: 1 in B; 2/5/11 in A; 3 in A+C; 4/6/9/10 in D; 7 in C;
8 in B (linked-client snapshot chosen and documented); 12 in B+C;
13 in B (payment-failure fallback; cancellation reason not added).
Later addenda supersede earlier probe interpretations, especially P7/P16,
P14/P24, P17/P25, E5/R3 tax casting, and P6's URL/card distinction.
The final review and external register below describe the current tree.

Evidence limits apply to **all** verification/count claims below. Saved
response bodies prove JSON shapes, echoed values, totals, and error codes;
except `p6b.headers`, they do not retain HTTP headers/status lines.
Fetch/clone dates and reviewer model attribution are coordinator-reported
provenance where no independently retained metadata establishes them.
HTTP status numbers are historical coordinator reports, **unverified
independently** here. Similarly, suite totals, assertions, red-first runs,
Pint/PHPStan passes, mutation kill counts and byte-identical restores are
reported historical results, **unverified as executions** unless a raw
result is retained; review prompts/approvals alone do not prove execution.
No tests, network probes, or commits were performed in this review.
Round outcomes are read from the saved verdicts and retain their stated
scope (including delta-only and conditional approvals).

## Verified correct (historical pre-fix assessment)

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
impact `total`"); only `total_override` changes the charge *among these four
aggregate overrides*. Two separate total-affecting inputs sit
outside that statement: `debt` (added/subtracted to invoice total) and
per-product `total_price_override`. Our `total_override` mapping is
correct and `fromCheckoutable()` (always sets a reconciled
`total_override`) has no overcharge path — but bare `.discount()`
callers do (see bug 4).

## Bugs (historical; all dispositioned in Batches A–E)

1. **Double tax at line level** — `CartCheckoutLineItem` passes item
   `tax_percent` through while the item price already includes tax
   conditions (`MoneyTrait::getRawPrice()` applies ALL item conditions).
   CHIP adds the percent a second time. Charge is safe (`total_override`
   governs); invoice line display inflates. Fix: report `0.0` in the wrapper.
   - `packages/cart/src/Models/CartCheckoutLineItem.php`
   - Note: the blanket-zero proposal was superseded by Batch B
     condition-scoped zeroing. Rendered invoice appearance was not probed;
     it is parked below, not a current implementation ticket.
2. **`purchase.total` sent but absent from spec** — `fromCheckoutable()`
   sets `$data['purchase']['total']`; `PurchaseDetailsBody` has no `total`
   field. Strip it; keep `total_override`. Fix must also update the local
   readers/validators of `total`.
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~line 255)
   - `packages/chip/src/Services/Collect/PurchasesApi.php` (~lines 91, 827-836)
   - Note: SDK `PurchaseDetails::$total` is a public serialized model
     field, not evidence of server writability. P1 later proved this
     supplied value ignored for the tested create payload.
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

## Docs gaps (historical; addressed in Batch D and later addenda)

- `09-webhooks.md`: no retry-protocol specifics — spec says 8 additional
  attempts, 36h window, at-least-once duplicates possible, sequential per
  source object.
- No test-card docs — spec: `4444 3333 2222 1111` (non-3DS) and
  `5555 5555 5555 4444` (3DS), CVC `123`, any name; anything else fails
  in test mode. Note: spec's own expiry wording conflicts (≥ current
  month vs > now) — the later card attempt was inconclusive.
- No `?active=` param mention (spec'd pre-select alternative to `?preferred=`).
- NEW: `Idempotency-Key` header; error taxonomy + `__all__` shapes;
  `remember_card=on` token-enrolment step; card Direct Post
  prerequisites/field contract; `debt` semantics; `payment_methods`
  amount rationale (RM 10 / Atome minimum).

## Coverage gaps (historical; dispositions recorded below)

- CLOSED (Batch E): fluent-builder gaps — `paymentMethodWhitelist()`
  setter (E1), client `cc`/`bcc`/legal/brand/registration/tax/bank
  setters (E1), checkout billing-address mapping (E3).
  (`ClientDetailsData` serializes all 22 spec fields;
  `Subscription.php` upcoming-invoice preview is display-only —
  both checked, never gaps.)
- CLOSED in Batch B: refund-union caveats. `Payment` inherits the
  generic `type` field from `BaseModel` (fresh spec :1903/:1502), though
  its literal values are not enumerated. `PurchasesApi::refund()` now
  recognizes the purchase shape before the type fallback; the cashier
  fake has `pendingRefundOnce()` and slow-path tests. Successful live
  refund shapes remain unverified and are parked, not an open code fix.
- CLOSED (Batch D + E): `WebhookBody.all_events`/`public_key` inputs
  (D4 docs, verbatim passthrough); `tags`, `language`, `debt`,
  `timezone`, `issued`, `email_message`, `request_client_details`,
  per-product `total_price_override` (E1 setters);
  `balance()` filters (E2); validated bounds (E4: `tax_percent`
  0–100, `notes` 10k, `creator_agent` 32, `platform` enum,
  256-char name/category, client lengths, cc/bcc 254).
- CLOSED by P2a/P2b and E5/R3: numeric tax is accepted in the sampled
  requests; outbound strings and response strings are preserved.
- CLOSED by T2/P24: fractional quantities and zero quantities supported;
  current bounds and exact line arithmetic are recorded in cont. 3.
- SDK-only scope (in neither spec nor package; not a gap): Billing
  Templates family. CORRECTION: the earlier card-form-helper claim has
  no supporting file in the saved v2.1.0 SDK and is retracted.

## SDK-vs-spec contradictions (do NOT "align" to the SDK)

- SDK `publicKey->get()` expects a `{"public_key": ...}` object, but the
  spec returns a JSON-encoded PEM string. CORRECTION: with the SDK's
  concrete `GuzzleClient`, the `array|stdClass` return type throws a
  `TypeError` on that string before the resource can run; its resource
  separately expects the object field. Its object-shaped mock does not
  exercise the concrete-client failure.
  Local string handling agrees with spec and P19.
- SDK `examples/api/webhook.php` retrieves the company-wide key, but
  its comment explicitly describes `success_callback`, for which that
  key is correct (fresh spec :133). Applying that example to a registered
  Webhook would require its per-webhook key (:131); the earlier blanket
  claim that the example itself uses the wrong key is retracted.
- SDK retries POST mutations on 5xx/429 with no idempotency (possible
  double-mutate); local GET-only retry policy is deliberately safer.
  Historical local omission CLOSED in Batch B: 429/`Retry-After` handling.
- SDK refund always returns Purchase; spec prose describes Payment or
  Purchase(`pending_refund`), while its 200 response schema references
  only Payment (:380/:385). Local handles both described outcomes.
- SDK money epsilon-coercion is more forgiving (`"108.00"`, float noise)
  than the local strict minor-amount validator. `Support/Money.php` rounds
  float-converted inputs within 1e-9 of an integer; this is not proof of
  exactness for all large numeric strings. The earlier universal "never a
  wrong amount" assurance is withdrawn.

## Residual external unknowns (historical; superseded by probe dispositions)

- How CHIP's server combines conditioned line prices with `tax_percent` for
  invoice display — arithmetic settled by P9/P25; rendered appearance
  parked without a new code ticket.
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

## Batch A audit deferrals (historical; F11 closed in Batch D)

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
  8 widened purchase mutation methods + bridges + facade, leaf/reference/monthly/invoice
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
  helpers + 8 mutations (9-vs-8 reconciled); mutation counts are
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
  E5 (as-given string tax, `from()` cast kept then; superseded by R3), E6 (API-site
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
- Implementation complete: Batches A–E approved (Batch E Bunny's
  approval was conditional on the docs-only B3 fix, now present).
  This is the historical batch close-out; later addenda record probes.
  Merging the branch is outside this review's scope.

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
- P3 `quantity: "1.5000"` → reported 201, honored (total 1500,
  echoed as given). The then-current client rejected fractions;
  this historical T2 ticket was closed in the next addendum.
- P4 `Idempotency-Key` replay (same key, then identical
  payload) → two distinct purchases (reported 201 + 201). Header
  did not dedupe the tested purchase creates; other mutations were
  not replay-probed. The local ledger provides create dedupe. Docs updated.
  Re-proven with full artifacts after Codex R2 (`p4redo.body.json`
  + `p4redo.key.txt` + `p4redo-a/c.json`): same body, same key,
  distinct ids.
- P5 `GET billing_templates/` → reported 200, empty paginated result.
  That list route responded; other Billing routes were not probed
  (SDK-only scope; future package surface parked, no action).
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
- P6 `direct_post_url` params: UNPROBED at this stage (later
  attempted in cont. 2; field `None` on the plain probes;
  a later redirect-bearing card probe obtained the URL).

Historical unresolved questions at this stage (later dispositions below): 403/409
emission shapes, current-month card expiry acceptance,
`direct_post_url` param behavior, spec inconsistencies
(`PurchaseBody.required` vs client XOR prose — P7 leans
optional; `__all__` object vs array; `PublicKey` string
vs SDK object).

## Addendum 2026-09-29 (cont.): T2 fractional quantities + P12–P15

- T2 implemented (historical > 0 bound, superseded by P24's >= 0):
  quantities accept finite numerics below 2^53 with at most 4
  decimal places (int, whole/fractional float, numeric string).
  P3 proves `"1.5000"` created a purchase; P18's mixed request
  had no field errors for `"1e2"`, `" 2 "`, `"+2"`, but failed
  overall on its fourth line, so successful creates for those
  three spellings are unverified. 5dp+
  is a server 400 `max_decimal_places`, mirrored locally
  by string-counting decimals (float casts would collapse
  forms like `"1.0000000000000001"`); >= 2^53 rejected as
  out-of-range as a local safety bound, not a proven server limit;
  line math later moved from float64 to BCMath. Both `normalizeQuantity`
  sites (builder + API) widened to `int|string`, emitting trimmed strings
  (floats stringify); both subtotal sites use the shared
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
  rejected; the inference that quantities must be > 0 was wrong,
  corrected by P24's zero line in a nonzero purchase). P15 bad key → 401
  `{"__all__":[{"message":"Incorrect secret_key",
  "code":"authentication_failed"}]}` — auth failures are
  reported 401, and top-level `__all__` was an ARRAY in those
  sampled envelopes (auth failure, method error, zero-total guard).
- Verification: builder/interface/API/Data files green
  (38 + 14 + 65 + 93), full Chip suite 893 passed
  (2583 assertions), Pint PASS (incl. mb_* sweep of the
  new helper), PHPStan L6 clean.
- Luna T2-R2 APPROVEd (BLOCK refuted with the override
  arithmetic worked through; no Σ-discount scope needed).
  Its two non-blocking precision notes were both fixed in
  kind: decimals string-counted per decimal-field rules,
  bound tightened 2^63 → 2^53 — micro-confirm R3
  dispatched on that delta; T2-R3 confirmed the R2 approval
  (`/tmp/panel-chip/audit-T2-luna-R3-out.txt`).
- P16 no-client create → 400 `purchase_client_or_id_required`
  (`__all__` array): client XOR client_id IS required
  server-side — answers draft Q4 (dropped from the ticket).
- P17 price 100 / discount 1 / qty 1.5 → total 149.
  CORRECTION (P25, 2026-09-29): this case coincides under
  both per-unit and per-line discount math
  ((100−1)×1.5=148.5→149 vs 150−1=149), so the original
  "matching exactly" claim never distinguished them. P25a
  settles it: discount is per-line. The ±1 note is retired
  with the old accessor.
- P18 quantity forms: `"1e2"`, `" 2 "`, `"+2"` had empty
  per-product error objects in the mixed failing request;
  `"1.55555"` → reported 400 `max_decimal_places`. Local 4dp rule
  mirrors the server; float dust below 14 significant
  digits stringifies away before the check (validates the
  wire form).
- Luna T2-R1 BLOCKed on discount-rounding parity; historically rebutted
  with P17 (which did not distinguish per-unit/per-line math; P25
  later superseded that parity argument). The response check
  compares server-total vs caller-override, neither side
  from the divergent pair; mixing the old accessors to pin
  overrides threw locally first. Its three non-blocking
  notes all produced real fixes: 4dp bound (P18), 2^63
  range bound, draft rewording (Q4 dropped, Q5/Q2/Q3
  qualified as tested-vs-untested). Card-flow probe for
  `direct_post_url` skipped at this stage (later attempted
  in cont. 2): needs test-card tokenization
  through the card form, disproportionate to a support
  question — recorded as untested, not untestable.

Support ticket draft (send as-is; evidence from sandbox
probes P1–P27, brand in `~/Herd/unfair/.env`):

> Subject: Collect API — status codes, error envelope,
> and one doc conflict
>
> Hi CHIP team — we're integrating against Collect
> (sandbox-verified; 97 saved JSON responses).
> Three items we could not settle:
>
> 1. Status codes: your errors guide lists 403
>    `forbidden`, 409 `conflict`, and 422
>    `unprocessable_entity`, but our recorded probe statuses
>    include only 400, 401, 404, and 405 for errors (saved JSON
>    bodies retain the codes/shapes; API status headers were
>    not retained). The recorded 400s cover
>    validation plus state violations (a repeat cancel,
>    and release/refund/capture on a fresh purchase,
>    return 400 `purchase_*_wrong_status` — the first
>    cancel succeeds; tokenless charge returns 400
>    `invalid_recurring_token`). Your guide names
>    capture-on-captured as a 409 case and over-capture
>    as a 422 cause — we reached both prerequisite
>    states live (card-paid pre-auth hold) and both
>    returned 400: repeat capture gives
>    `purchase_capture_wrong_status`, over-capture gives
>    field `max_value`; a second full refund gives 400
>    `purchase_refund_not_possible`. Which operations
>    actually return 403/409/422? (Side note: bad keys
>    return 401 `authentication_failed`, not the guide's
>    `unauthorized`.)
> 2. `__all__` shape: the spec shows an ARRAY at :301
>    and :327 but an OBJECT at :1269. The `__all__`
>    values in all 15 saved error envelopes were arrays
>    (13 top-level, plus 2 nested under product errors;
>    other field errors use keyed/nested shapes). Is the
>    object form ever emitted, or is :1269 stale?
> 3. Idempotency docs conflict: the errors guide says a
>    repeated body with the same `Idempotency-Key`
>    "will return the same result without performing
>    the action twice", but our sandbox replay
>    (identical body, same key) created a second
>    purchase. Please confirm the header is currently
>    ignored for purchase creation and either honor it or
>    fix the guide. Other mutations were not replay-probed.
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

- P6 URL-param question CLOSED (card-flow success unverified):
  `?preferred=`/`fpx_bank_code` are CHECKOUT-URL
  features (direct-post intro docs, official example with
  `MB2U0227`), not `direct_post_url` features — the
  residual's premise was wrong. Live: checkout URL +
  params → 302 to `payments.chip-in.asia` (headers saved
  in `p6b.headers`; params consumed, not echoed); page
  content is JS (curl cannot confirm preselection).
- P19 `GET public_key/` → 200 JSON string (PEM). Spec
  canonical; the official SDK concrete client cannot handle it — its
  client types `array|stdClass` (`GuzzleClient.php:45`,
  `json_decode` passthrough at :96) while the resource expects
  `['public_key']` (`PublicKeyResource.php:20`, test
  mocks that shape), so static inspection indicates a
  `TypeError` against live. SDK-side skew, reported in
  the draft FYI. Old Q5 dropped.
- P20 expiry: "no earlier than current month/year" on
  BOTH the payment-link and pre-auth docs pages, plus
  spec :88 — three saved passages supporting current-month-valid,
  conflicting with spec :160 ("greater than now"). Live card attempt
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
- P24 quantity bound: `quantity: 0` line → purchase
  created (`created`, id echoed, qty `0.0000` —
  `zeroline.*`); `quantity: -1` → 400 `min_value`
  "Ensure this value is greater than or equal to 0."
  (`negqty.*`). Server bound is zero-or-greater;
  client `normalizeQuantity` and the docs rule updated
  to match (`< 0` rejects, `zero or greater` message).
- 403/409/422: still unobserved in the saved probes. The errors
  guide DOES document all three (403 scopes, 409 state,
  422 semantics) plus a 401 `unauthorized` code that
  mismatches live `authentication_failed` — and its
  Idempotency section promises server dedupe,
  contradicted by P4. Kept as draft Q1 (sharpened) +
  new Q4 (idempotency docs conflict).
- `__all__`: the values in 11/11 then-observed top-level
  envelopes are arrays (verified by artifact inventory, re-confirmed
  by Luna). P25g/h later added 2 nested product envelopes, also arrays;
  other field errors use keyed/nested shapes. Versus spec :1269 object — kept as draft Q3
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
- Luna probe-R7 APPROVEd **only the evidence clause**, explicitly
  declining to assess other claims (`audit-P-luna-R7-out.txt`).
  Prior draft findings were closed across the earlier deltas; this
  was not a fresh approval of every probe. P1–P23 questions had
  evidence dispositions or support referral; P6/P20's actual card
  success/expiry remained unverified. See the final external register.

## Addendum 2026-09-29 (cont. 3): branch-vs-intro verification pass

- Coordinator re-verified the full branch diff against
  the live intro/conventions prose (fresh spec re-fetch:
  prose-only delta). 6 findings raised; 4 fixed, 2
  self-invalidated on re-check:
- FIXED: quantity `<= 0` client reject contradicted
  live (P24: `quantity: 0` → `created`, qty echoed
  `0.0000`; `-1` → 400 `min_value` "greater than or
  equal to 0"). Both `normalizeQuantity` copies now
  `< 0` with `zero or greater` message; tests + docs
  rule updated.
- FIXED: stale `Idempotency-Key "honoring unverified"`
  comment in `BaseHttpClient` — sandbox P4 proved it did not
  dedupe purchase creation; comment now has that endpoint scope.
- FIXED: country `maxLength 2` rationale in checkout
  builder comment + `06-payment-gateways.md:57` —
  sandbox P10 accepts the full name; code kept
  (code is the safe form), rationale corrected.
- FIXED: `?active=` FPX example carried an
  `fpx_bank_code` param the docs never specify for
  `?active=` — dropped to the bare form.
- INVALID (own misread, reverted): whitelist docblock
  is accurate — `visa`/`mastercard` ARE in the list
  and `EWallet` (incl. Atome) is a genuinely different
  direct-post key space.
- INVALID (diff read backwards): the branch REMOVED
  the `array_reverse` from `PaymentFailedHandler`
  (bb4b16971); newest-first + top-level `error_code`
  fallback is the current, correct state. No change.
- Luna verify-review BLOCKed (1 finding, verified with
  commands before acting): the branch removed the
  caller-computed total from
  `FakeChipCollectService::createCheckoutPurchase`,
  exposing `FakeChipClient::calculateTotal`, which
  int-casts quantity and ignores tax/TPO (P17 case:
  fake 99 vs server 149). Coordinator-confirmed via
  `git show bb4b16971` + artifact read; the int-cast
  itself is pre-existing, the exposure is branch-caused.
- P25 line-total formula probes (a–f reported 201; g/h reported 400):
  P25a 100/1/3 → 299 (discount PER-LINE, overturns the
  P17 "matching exactly" note above); P25b two 10/6%
  lines → 22 (rounding per-line); P25c 100/10/1/10% →
  99 (tax on the NET line); P25d 100/1.555/10% → 171
  (SINGLE round, not gross-then-tax); P25e TPO
  2500+10% → 2500 (TPO final even with tax); P25f
  100/150/3 → 150 (discount above unit price
  accepted); P25g 100/500/3 → 400
  `product_subtotal_negative` ("Discount can't be
  larger than price * quantity!", `__all__` array —
  first nested product `__all__` array, after 11 top-level sightings).
  P25h TPO+overdiscount also rejects with that code (second nested
  array). Inferred server formula on these samples: validate net
  >= 0 first, then TPO ?? round_half_up((price×qty−discount)×(1+tax/100))
  per line. Artifacts `p25a–h.*`.
- Fix: shared `ProductData::lineTotalMinorUnits`
  (proven formula); `getTotalPrice` rewritten on it;
  `getDiscountTotalInCents` now pass-through (per-line);
  API discount bound `discount > price` →
  `discount > price×qty` (old bound rejected
  server-accepted P25f payloads); fake
  `calculateTotal` uses the shared primitive. Tests:
  8 probe-mirror line cases, accept/reject bound
  cases, fake 149/158/2500 cases; repinned 37820→41139
  (×2), 250→150, 149→199. Docs rule rewritten.
  Verify: Pint clean; PHPStan L6 clean (chip,
  checkout, cashier-chip); Chip 898, CashierChip 536,
  Checkout 262 passed.
- Luna R2 BLOCKed (2 findings, both reproduced before
  fixing): (1) float64 misrounds 100 × "1.005" to 100
  (true 100.5 → 101) — confirmed via `php -r`;
  (2) the fake clamped P25g over-discounts to a
  zero-total purchase instead of rejecting. Fix: exact
  BCMath decimal math in a shared `decimalParts`
  parser (ints, decimal + exponent strings);
  `lineTotalMinorUnits` + `multiplyMinorUnits` exact;
  negative nets throw `ChipValidationException`
  (mirrors `product_subtotal_negative`, inherited by
  the fake); API bound uses exact
  `discountExceedsLineGross` (200 × "1.005" = 201
  boundary proven misplaced by float, now passes);
  `ext-bcmath` added to `packages/chip/composer.json`
  (precedent: ext-curl/ext-intl). Tests: half-cent +
  exponent + throw + boundary-accept + fake-reject
  cases. Verify: Pint clean; PHPStan L6 clean; Chip
  901, CashierChip 537, Checkout 262 passed. R3
  dispatched.
- Luna R3 BLOCKed (5 findings, all verified before
  fixing): (1) int overflow — 1025 × (2^53−1)
  saturated to PHP_INT_MAX with a warning
  (reproduced via autoload); (2) unbounded exponent
  — TaxPercent accepts '1e-1000000000' (reproduced),
  which would str_repeat a billion zeros; (3a) fake
  silently substituted non-numeric qty/tax
  (code-read, own code); (3b) TPO-before-net order —
  P25h probe: server 400s `product_subtotal_negative`
  for TPO+overdiscount, so validation runs first;
  (3c) negative rounding truncated toward zero;
  (4) from() float-cast tax strings (22dp
  '0.4999…' became 0.5); (5) WebhookFactory int-cast
  qty totals (code-read). Luna confirmed all
  recomputed cases, the parser on ordinary forms,
  and ext-bcmath correct. Fix: shared sign-aware
  `roundHalfUp` (away from zero, throws past int
  range); net check before TPO (P25h); 1024-digit
  precision guard in `decimalParts`; from()
  preserves string tax; fake throws on non-numeric;
  WebhookFactory uses the shared primitive. Tests:
  P25h-order, overflow ×2, precision ×2, sign-aware,
  string-tax, fake-reject, factory-fractional cases.
  Verify: Pint clean; PHPStan L6 clean; Chip 906,
  CashierChip 538, Checkout 262 passed. R4
  dispatched.
- Luna R4 BLOCKed (3 items + 1 docs catch; P25h
  order, parser forms, and ext-bcmath confirmed):
  (1) `roundHalfUp` rejected valid PHP_INT_MIN
  (own conservative guard, flagged in the brief);
  (2) the 1024-digit guard vs TaxPercent-accepted
  values — settled by probe, not dispute: P26a/b/c
  prove the server caps tax at max_digits=5 /
  max_decimal_places=2 / max_string_length, so the
  local rule now mirrors that (Luna's '1.'+1024-zeros
  example is server-rejected); (3) fake/factory
  validation gaps. Fix: exact PHP_INT_MIN edge;
  `TaxPercent` precision mirror via shared public
  `decimalParts`; fake `validatedLine` (integer
  minors ≥ 0, qty numeric ≥ 0, shared tax rule —
  all ChipValidationException); factory setter
  passes discount/tax/TPO through and merges
  product overrides before computing the total;
  stale "responses parse tax to float" doc line
  corrected. Tests: TaxPercent suite (accept/range/
  precision), int64 edges, 8-case fake-reject
  dataset, factory setter + override consistency.
  Verify: Pint clean; PHPStan L6 clean; Chip 912,
  CashierChip 545, Checkout 262 passed. R5
  dispatched.
- Luna R5 BLOCKed (1 item; MIN edge, fake/factory,
  and P26 verdicts confirmed): `decimalParts`
  returned ['0','1'] before computing shift/guard,
  so TaxPercent accepted '0.000' and thousand-zero
  strings. Fix direction probed first: P26d tax
  '0.000' → 400 `max_decimal_places`, so reject is
  the faithful mirror. Fix: shift/guard computed
  before the zero return; zero keeps its decimals
  in the denominator. Tests: zero-dp accept/reject
  + zero-qty line-total case. Verify: Pint clean;
  PHPStan L6 clean; Chip 913, CashierChip 545,
  Checkout 262 passed. R6 dispatched.
- Luna R6 APPROVEd: blocking findings resolved,
  all changes lean back to the official CHIP API.
  Verification loop closed (verify → BLOCK → R2 →
  BLOCK → R3 → BLOCK → R4 → BLOCK → R5 → BLOCK →
  R6 APPROVE). See the current external register below.

## Review 2026-09-30: record accuracy and evidence inventory (round 1)

Reviewed `bb4b16971^` through `e980114cd` plus the uncommitted tree,
including all four named commits. This pass is independent of Luna's
code approval; its settled findings were not reopened without contrary
source evidence. The existing uncommitted cleanup is wording/test-label
cleanup, plus earlier draft/expiry corrections; it introduces no new
wire behavior. P-labels and round references remain intentional here.

Recounts: both saved specs contain **33 operations**; current service
paths cover 33/33 (the historical 31/33 omitted webhook PATCH and statement
POST). The fresh spec and local enums contain matching sets of **27 events**
and **21 statuses**. `ClientDetails` has 20 direct properties plus 2
inherited `BankAccount` properties, matching the DTO's **22** fields.
`RequestClientDetail` has **12** cases; the builder whitelist has **16**
values, matching `PurchaseBody`'s enum. `PurchasesApi` has **12 optional
key parameters with defaults** (create, 3 helpers, 8 mutations); its
required resolver parameter is additional. Historical 11/11 C and 6/6 E
mutation results remain reported execution counts, not a new recount
of executed tests. The 13 caller dispositions are in `agree-C.md`.

Snapshot provenance: original spec 184000 bytes, fresh spec 185191 bytes;
both OpenAPI 3.1.0. Their diff changes only recurring-token prose (intro
:68/:70 and response :2860/:2865), not schemas/operations or line numbering.
SDK checkout is tagged `v2.1.0`. Original/revised spec citations :88, :160,
:301, :327, :1269 and :3015 were read and agree with the quoted passages.
Historical approximate code/test pointers are not current line citations.

All **217** files in `/tmp/panel-chip/probes/` are accounted for below:
68 request bodies, 98 response `.json` files (97 valid JSON + empty
`p8del.json`), 27 empty `.err` files, 10 HTML files, 10 screenshots,
2 prose snapshots, 1 header file and 1 saved replay key. This is an artifact count, not a
count of unique network calls. For each listed stem, all associated
`.body.json`, `.json`, `.json.err`, HTML/header/key siblings are included.
No inference of HTTP status is made solely from a filename such as
`auth403`: its body says `authentication_failed`, not `forbidden`.

| Probe / disposition | Artifact stems under `/tmp/panel-chip/probes/` | Body evidence / limit |
| --- | --- | --- |
| P0 baseline | `p0` | `created`, `is_test: true`, total 100. |
| Diagnostic attempts, parked as setup evidence | `diag`, `diag2`, `diag3` | GET-not-allowed envelope; two baseline creates total 100. `diag.body.json` is the saved create body, not proof it accompanied the GET. |
| P1 | `p1`, `p1get` | Supplied total 999999; create and retrieve both total 100. |
| P2a/b/c | `p2a`, `p2b`, `p2c` | Numeric 6 -> `6.00`/10600; `1e2` -> `100.00`/20000; category null -> `null` field code. |
| P3 | `p3` | `1.5000` -> total 1500. |
| P4 and redo | `p4a`, `p4b`, `p4c`, `p4redo-a`, `p4redo-c`, `p4redo.body.json`, `p4redo.key.txt` | Initial a/b bodies differ by reference; c is the identical replay report. Redo retains a common body/key and distinct resulting IDs. Request headers themselves were not retained. |
| P5 | `p5` | Empty paginated billing-template result; no inference that every Billing route is live. SDK-only family parked. |
| P6 / card preparation and attempt | `p6.html`, `p6b.headers`, `p6b.html`, `card`, `cardpost.html`, `cardpaid`, `paypage.html` | Empty p6 bodies do not prove selection. Headers prove 302 to payments host without query. Card POST returns JS-dependent form; final purchase `viewed`. |
| P7 | `p7` | Required errors for purchase and brand, not proof client is optional; superseded by P16. |
| P8 | `p8`, `p8del` | Both inputs accepted; response normalizes `events` to `[]` with `all_events: true`. Empty delete body alone cannot verify reported 204 or remote deletion. |
| P9 | `p9`, `p9get` | 10000 + 6% -> 10600, no separate computed subtotal/tax fields. |
| P10 | `longcountry`, `c24`, `c200`, `p10b` | Malaysia -> MY; USA -> US; garbage -> `invalid_choice`; shipping full name -> US. |
| P11 | `negtpo` | Negative TPO -> `min_value`. |
| P12/P13 | `round`, `tie` | 156 + 50 = 206; 156 + 156 = 312. |
| P14 | `zero` | Zero-total purchase -> preauthorization/skip-capture error; not a quantity-minimum result. |
| P15 | `auth403` | Top-level array with `authentication_failed`. |
| P16 | `noclient` | `purchase_client_or_id_required`. |
| P17 | `disc` | 100 x 1.5 - 1 = 149 after half-up; not a discriminating discount probe. |
| P18 | `forms` | First three product error objects empty; fourth quantity -> `max_decimal_places`; entire request failed. |
| P19 | `pubkey` | JSON string containing PEM, not an object. |
| P20 corroboration | `docs-payment-link.md`, `docs-preauth.md` | Both say no earlier than current month/year; saved excerpts, not successful payments. |
| P21 | `dpu`, `dpuget.html`, `dpuget2.html` | Redirect-bearing create has Direct Post URL; the two GET bodies are byte-identical. That does not prove parameters ignored by later JS. |
| P22 | `both` | `purchase_client_or_id_only`. |
| P23 setup and actions | `cx`, `cancel1`, `cancel2`, `st`, `state-release`, `state-refund`, `state-capture`, `state-charge`, `get404` | First cancel `cancelled`; repeat/state ops have reported codes; charge token invalid; missing purchase `not_found`. |
| P24 | `zeroline`, `negqty` | Zero line plus normal line -> total 100 / zero echoed `0.0000`; negative quantity -> `min_value`. |
| P25a–h | `p25a`, `p25b`, `p25c`, `p25d`, `p25e`, `p25f`, `p25g`, `p25h` | Totals 299/22/99/171/2500/150; g and h reject overdiscount, including with TPO. |
| P26a–d | `p26a`, `p26b`, `p26c`, `p26d` | `6.555` -> 2dp; 30 fractional digits -> max 5 digits; 1100 padded zeros -> string too large; `0.000` -> 2dp error. |
| Supplementary arithmetic, recorded | `disctax`, `taxcombo`, `tpo`, `visdisc`, `debtneg` | Half-up((150-1)x1.06)=158; 300x1.06=318; TPO=2500; display discount 10 leaves total 100; debt -50 yields total 50. |
| Supplementary bounds, recorded | `notes`, `wl0` | 10001-char notes -> `max_length` 10000; empty whitelist -> `min_length`. |
| Supplementary method lookup, recorded | `paymethods`, `razer`, `wl-card`, all 16 `wl-{method}` success pairs | Lookup advertises 17 methods including `razer`; create rejects `razer` (`invalid_payment_method_whitelist`) and `card` (`invalid_choice`), accepts the 16 builder enum values. Lookup and create key sets differ. |
| P27 live card pay/refund/capture | `p6card`, `p6cardfail`, `p6preauth` (+ filled/result HTML/PNG), `refund1`, `refund2`, `cap-over`, `cap1`, `cap2`, `p6card-landing` | Card pay → `paid` (visa, flow `payform`, current-month 09/26 expiry accepted); wrong CVC → `error` with attempt `{code: validation_cvc_invalid}` object and no top-level `error_code`. Full refund → `type: payment, status: success`; second refund → 400 `purchase_refund_not_possible`. Pre-auth hold captures once (`type: purchase, status: paid`); over-capture → 400 field `max_value`; repeat capture → 400 `purchase_capture_wrong_status`. Checkout landing confirms bare `?active=` values incl. `card` on the payments host. |

There are **15/15 array-valued `__all__` occurrences**, in 15 response
files: 13 at the top level (`auth403`, `both`, `cancel2`, `cap2`, `diag`,
`get404`, `noclient`, `refund2`, `state-capture`, `state-charge`,
`state-refund`, `state-release`, `zero`), plus 2 at
`purchase.products[0].__all__` (`p25g`, `p25h`). No
object/string-valued occurrence was found. Other field errors use both
lists and keyed objects; this recount does not call all errors `__all__`.
The support draft's 15-envelope count agrees.

Round evidence was checked against the saved verdicts: branch verify/R2/R3/
R4/R5 BLOCK then R6 APPROVE; the distinct probe-draft R1–R6 BLOCK then
R7 clause-only APPROVE; T2-R3 confirmed T2-R2. Batch E's final Bunny
record is a conditional approval after B3, not a separate unconditional
R3 reinspection. Historical execution claims remain qualified above.

## Review 2026-09-30: independent official-API alignment

In this table, `fresh` means `/tmp/chip-docs/chip-collect-fresh.yaml`,
`SDK` means `/tmp/chip-php-sdk/lib/`, and probe stems refer to the complete
inventory above. ALIGNED means the change moves toward the documented
or observed API; it does not mean every server edge was reproduced.
Local stricter safety policies are distinguished from server rules.

| Change group and local surface | Verdict | Fresh spec / saved live evidence / official SDK cross-check |
| --- | --- | --- |
| Request-only product serialization; reject `purchase.total`; canonical/legacy fingerprints and frozen checkout keys (`ProductData::toRequestArray`, `PurchaseBuilder`, `PurchasesApi`, ledger, fake bridge) | ALIGNED | Fresh :3844/:3878 makes category a non-nullable string; :4013 omits request total; P1/P2c prove ignored total and rejected null. SDK `Model/Product.php:80` omits nulls, while `Model/PurchaseDetails.php:26` serializes total; that SDK field is not a server write contract. Local full `toArray()` still retains nulls. Legacy matches apply only to stored identities; the wire rejection remains. |
| Client XOR and linked-customer snapshot (`PurchaseBuilder::fromCustomer` and client setters; API validator) | ALIGNED | Fresh :3892 requires client, conflicting with :3913's client-or-ID prose. P16/P22 establish both XOR halves: **live behavior outranks the unconditional required list**. SDK `Model/Purchase.php` models both fields but does not enforce XOR. Linked clients are copied per :3913; the early return preserves that snapshot choice rather than updating a Client implicitly. |
| Fractional/zero quantities and exact gross math (both normalizers, `multiplyMinorUnits`) | ALIGNED | Fresh :3853 is string with minimum 0; SDK `Model/Product.php:18/:52` passes quantities through. P3/P12/P13/P18/P24 support fractions, half-up per line, 4dp and zero lines; P14 concerned zero total. Below-2^53 is a local guard, not a server maximum; strings are trimmed, not preserved byte-for-byte. |
| Per-line discount, net-base tax, final TPO, validation-before-TPO (`lineTotalMinorUnits`, discount bound, DTO accessors, fake/factory totals) | ALIGNED | Fresh :3866/:3872/:3882 describe line discount, added tax and final override; SDK `Model/Product.php:28/:33/:43` carries these fields without calculating them. P25a–h, disctax/taxcombo/tpo discriminate the actual formula. Net-negative rejection runs before TPO. Exact BCMath and integer overflow throws implement the inferred formula without float misrounding/saturation; they are local safety, not CHIP error codes. |
| Tax string preservation and range/precision validation (`TaxPercent`, `ProductData::from`, zero precision) | ALIGNED | Fresh :3872 specifies string, range 0–100 but no precision limit; SDK `Model/Product.php:55` preserves raw tax. P2a/b accept JSON number/exponent, so **live tolerance outranks the string-only schema for those samples**. P26a/b/d support 5 digits / 2dp, including zero; P26c supports rejection of its long input, not an exact universal string-length threshold. The 1024 parser cap is local. |
| Tax-conditioned cart lines zeroed, attribute tax included in checkout total/tax (`CartCheckoutLineItem`, `Cart`) | ALIGNED | Fresh :3872 plus P9/P25c/d apply tax on top of the supplied net line; the adapter must avoid resending baked tax. SDK `Model/Product.php:33` forwards tax unchanged and has no cart policy. Local wrapper zeroes only when a tax condition is present; attribute-only rates still flow through and are included in reconciled totals. Rendered invoice appearance is not verified. |
| Optional `Idempotency-Key` on resolved creates and eight mutations; service/facade/fake signatures; invoice option and monthly child keys | ALIGNED | Saved `chip-collect_overview_errors.md` Idempotency section specifies the header; fresh has no header definition. P4redo distinct IDs contradict the guide's create dedupe promise: **live create behavior outranks prose**. SDK `Resource/PurchasesResource.php:17/:59/:75` does not add keys. Local ledger remains fail-closed; mutation header honoring is unverified. The fake's keyed store/fingerprint conflict and reset clearing model the local integration, not remote dedupe; the one-shot pending-refund flag also resets. Independent checkout-builder path stays keyless. |
| No automatic mutation retries; 429 delay exception, Retry-After parsing, scalar error normalization, masked logging (`BaseHttpClient`, Collect/Send clients, `ChipApiException`) | ALIGNED for shared handling; Send-specific parity UNVERIFIABLE | Saved errors guide prescribes 429 backoff; fresh :301/:327/:1269 has competing `__all__` shapes, and all 15 saved envelopes are arrays. Parser accepts object/list/string, promotes only top-level code, keeps nested errors raw, and client-layer logging scope is explicit. SDK `Http/RetryClient.php:28/:63` retries any method on 429/5xx; `Http/GuzzleClient.php:69` misses `__all__`. P4 argues against mutation retry. No saved Send spec or live Send errors prove its own envelope/status contract; an official Send snapshot and retained errors would settle that parked claim. HTTP-date edge policy is local; no CHIP date header was captured. |
| Webhook PATCH, statement schedule/cancel surface and validation (`WebhooksApi`, `AccountApi`, `ChipCollectService`, facade/fakes) | ALIGNED | Fresh :865 PATCH, :980 POST, :1076 cancel and :3551 csv/xlsx + timezone; SDK `Resource/WebhooksResource.php:68`, `Resource/StatementsResource.php:20/:63` use the same paths/methods. No saved success probe for these new operations; static contract alignment is verified. |
| Balance filters and turnover widget/docs (`AccountApi::balance`, service/fakes, `AccountTurnoverWidget`) | ALIGNED | Fresh :918/:950 query filters and :3330/:3352 `incoming.turnover`/`fee_sell`; SDK `Resource/AccountResource.php:19/:34` forwards queries. Widget uses Unix from/to and one MYR currency, then resets all series on failure. Saved Collect probes do not exercise these account responses. |
| 16 setters, 12 request-detail cases, 16 whitelist values, API field caps, N2 email trimming (`PurchaseBuilder`, `RequestClientDetail`, `PurchasesApi`) | ALIGNED | Fresh :3890/:3936/:4013/:4083 and ClientDetailsBody :3482 plus BankAccountBody :3459 define nesting/types/caps; SDK `Model/Purchase.php`, `Model/ClientDetails.php`, `Model/PurchaseDetails.php` expose the same fields, with `Builder/PurchaseBuilder.php:82` using string issued. notes/wl0/razer/wl-card and the 16 successful whitelist artifacts support the applicable bounds/key space. Stricter nonblank guards are local policies, not separately probed server rules. |
| Checkout billing mapping and uncapped country names (`ChipPurchasePayloadBuilder`, N3 API address caps, docs) | ALIGNED | Fresh :3482 defines billing/shipping address fields; :3562 limits country code to 2 chars, contradicted by P10 full-name normalization: **live accepted names outrank maxLength prose**. SDK `Model/ClientDetails.php` forwards names/codes without that cap. Checkout deliberately emits country_code or nothing and joins trimmed line1–3; it does not guess from country name. |
| Payment-failure newest-first error and top-level fallback; refund union/slow fake path (`PaymentFailedHandler`, `PurchasesApi::refund`, `pendingRefundOnce`) | ALIGNED | Fresh :295 newest-first attempts, :380 pending-refund Purchase prose versus :385 Payment schema, :1903 BaseModel type; saved errors guide lists top-level webhook error_code. SDK `Resource/PurchasesResource.php:75/:84` always returns Purchase, so spec is authoritative for union mapping. P23 was a rejected refund; P27 adds the successful Payment leg (`type: payment, status: success`) — the pending-refund Purchase leg remains unsampled. Local handler keeps the first usable attempt then error_code; async fake and schema-shaped tests cover the pending branch. |
| Public-key documentation correction and string-safe local handling | ALIGNED | Fresh :895/:2443 and P19 PEM string agree with local client/publicKey. SDK `Http/GuzzleClient.php:45/:96` would throw TypeError before `Resource/PublicKeyResource.php:20` could return its object field; `Http/RetryClient.php:35` does not rescue TypeError. No new public-key wire behavior was introduced. |
| Card/FPX/e-wallet URL and testing docs; EWallet ShopeePay/Atome and case-insensitive lookup | ALIGNED (P27: live card success + current-month expiry proven) | Fresh :468/:3015 and saved direct-post intro/fpx/e-wallet/active/card prose distinguish checkout URL queries from card form POST. E-wallet table specifies shopee_pay without bank code and razer_atome/Atome; SDK `Model/Purchase.php:240/:381` carries direct_post_url; no card-form/wallet-query helper was found in the saved SDK. P27 card pay reached `paid` with 09/26 expiry; wrong-CVC failure shape confirms the handler reading. Expiry :88 vs :160 stands as a prose nit, behavior settled. |
| `ext-bcmath`, response-string fixtures, probe-mirror/bound/fake/factory tests and remaining label cleanup | ALIGNED | Dependency enables exact integer/decimal arithmetic for the P25 formula; SDK `Support/Money.php` likewise insists on integer minor amounts but uses epsilon coercion. Added assertions express saved body outcomes and the documented local guards, not new API requests. Cleanup changes test names/comments, preserving audit labels; no tests were executed here. |

No verified **AGAINST** group was found. In particular, the apparent
contradictions were checked against both sides: country maxLength versus
accepted names, tax string type versus accepted numbers, client required
list versus XOR, SDK public-key/refund shapes versus spec/live, and the
idempotency guide versus repeated creates. Additional SDK-only claims were corrected: no saved card-form helper,
the webhook-named example explicitly describes success_callback, and
money epsilon coercion is not a universal exactness guarantee. The record
now names the controlling source and does not generalize sampled behavior to all calls.
Package docs `04-usage`, `07-chip-collect`, `09-webhooks`, `10-api-reference`
and checkout `06-payment-gateways` agree with the audited behavior;
quantity-form/upper-bound and expiry wording were corrected in this pass.

### Parked limits (no active fix or probe requested)

Rendered invoice display; the pending-refund Purchase leg (P27 settled
the successful Payment leg); Send-specific error parity;
unsampled numeric spellings and exact server
upper/string-length bounds; real Retry-After headers/maximum; and the
unimplemented SDK-only Billing family are explicitly parked. Their claims
are unverified where stated, not hidden tasks. A retained successful
response/render, official Send evidence, or targeted boundary request
would settle the corresponding empirical limit. Historical suite/mutation
execution totals likewise need raw runner logs; this review did not run
checks to reconstruct them. Branch merge is outside the authorized scope.

### Current external register (only active items)

1. **Unsent support draft**, three questions: 403/409/422 emission,
   object `__all__` envelope, and create idempotency guide conflict
   (card-expiry question answered by P27 probe and dropped; see below).
   The draft above is consistent with saved bodies; sending it and receiving
   CHIP's answer remain external. HTTP numbers are historical reports as
   qualified above, not status proofs reconstructed from JSON.

## Addendum 2026-09-30 (cont.): P27 live card/refund/capture — P6 closed

- Playwright + Chromium drove the hosted checkout with the documented
  sandbox test card (non-3D `4444…1111`, CVC 123). The landing page
  links each method with a bare `?active=` value (`fpx`, `fpx_b2b1`,
  `card`, `razer`, `crypto_coin`, `dnqr`, `duitnow_qr`,
  `mpgs_apple_pay`, `mpgs_google_pay`, `razer_atome`, `shopee_pay`);
  card lives on the payments host (`…?active=card`).
- Card pay with current-month expiry 09/26 → `paid` (visa, flow
  `payform`, `expiry_month: 9`). Former Q2 (card expiry) answered
  empirically — dropped from the support draft (the :88/:160 prose
  conflict stands as a doc nit, not a question). Draft renumbered:
  former Q3 → current Q2 (`__all__` shape), former Q4 → current Q3
  (idempotency guide).
- Wrong CVC → `error`, attempt `successful: false` with object error
  `{code: validation_cvc_invalid}` and NO top-level `error_code`.
  The handler's message-first object reading is confirmed against
  this live shape; regression test pins it. No code change needed
  (false alarm caught by reading the handler first).
- Full refund of the paid purchase → 200 `type: payment, status:
  success` — settles the parked refund-union question on the Payment
  leg, matching the `payment` match arm. Second refund → 400
  `purchase_refund_not_possible` (top-level `__all__` array).
- Pre-auth (`skip_capture`, top-level — a nested attempt silently
  behaved as a normal pay; coordinator-reported, artifacts overwritten
  by the corrected re-run): card pay → `hold`; capture once → 200
  `type: purchase, status: paid`; over-capture → 400 field
  `max_value`; repeat capture → 400
  `purchase_capture_wrong_status`. The guide's 409/422 cases did not
  materialize — Q1 strengthened with this evidence, still open.
- Counts: 217 probe files (98 response JSON, 97 valid); 15/15
  `__all__` arrays (13 top-level + 2 nested). Draft is now 3
  questions. P6 closed; the only remaining external is sending the
  support draft.
- Sol confirm loop on this delta: R2 BLOCK (stale draft scope,
  superseded opens, Q2 numbering — all fixed), R3 BLOCK (one stale
  13-envelope count — fixed), R4 APPROVE. No gaps; no open issues
  beyond sending the support draft.
