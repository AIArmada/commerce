---
title: CHIP Collect — Open Support Questions (Full Context)
status: current
---

# CHIP Collect — 3 Open Support Questions

The full-context record behind the unsent CHIP support ticket: what each
question asks, what the official docs say, what we tried live, and where the
evidence lives. The sendable ticket itself is in
[Appendix A](#appendix-a-sendable-ticket-as-is); the audit draft it mirrors is
in `audits/chip-collect-spec-review-2026-09-28.md` ("Support ticket draft").

## Status and how to send

- **Status:** UNSENT. Sending it and receiving CHIP's answer are the only
  remaining external items in the spec-review program. No code is blocked on it.
- **To:** `support@chip-in.asia` (per the errors guide's "When to contact
  support" section).
- **Include:** `purchase_id`, request timestamp, and full response bodies
  (the guide asks for these; our bodies are the probe artifacts below).
- **Sandbox brand:** in `~/Herd/unfair/.env` (never paste the secret key).

## Evidence base and its limits

- **Probes:** P1–P27 against the CHIP sandbox, 217 probe files, 98 response
  JSON (97 valid), collected 2026-09-28/29. Uncommitted at
  `/tmp/panel-chip/probes/` — `/tmp` is ephemeral, so this doc and the audit
  are the durable record; re-probe if the directory is gone.
- **Saved official sources:** `/tmp/chip-docs/` — `chip-collect.yaml` and
  `chip-collect-fresh.yaml` (OpenAPI snapshots), `chip-collect_overview_*.md`
  prose pages, `introduction.html`, `llms.txt`.
- **Limits (read before citing):** saved bodies prove JSON shapes, echoed
  values, totals, and error codes, but — except `p6b.headers` — they do **not**
  retain HTTP headers/status lines. Status numbers below are historical
  coordinator reports, not status proofs reconstructed from JSON. Request
  headers (including the sent `Idempotency-Key`) were likewise not retained;
  the redo key survives as `p4redo.key.txt` and the shared body as
  `p4redo.body.json`.

## Question 1 — Which operations actually return 403 / 409 / 422?

### What the guide says

Live page `https://docs.chip-in.asia/chip-collect/overview/errors`
(verified 2026-09-30; content-identical to the saved
`chip-collect_overview_errors.md`) lists, among others:

| Status | `code` | Guide's meaning / example |
| --- | --- | --- |
| 401 | `unauthorized` | API key missing, malformed, or invalid |
| 403 | `forbidden` | Key lacks access; confirm scopes in the merchant portal |
| 409 | `conflict` | Not valid for the resource's current state — "you cannot capture an already-captured purchase" |
| 422 | `unprocessable_entity` | Well-formed but semantically invalid — "amount exceeds the remaining capturable balance" |

### What we observed

Across all probes, recorded error statuses were only **400, 401, 404, 405**.
State violations consistently came back as **400 with `purchase_*_wrong_status`
codes**, never 409:

| Trial | Probe | Reported status | Body code | Artifact |
| --- | --- | --- | --- | --- |
| Bad secret key | P15 | 401 | `authentication_failed` (NOT the guide's `unauthorized`) | `auth403.json` |
| Unknown purchase id | — | 404 | `not_found` | `get404.json` |
| Wrong method | — | 405 | `method_not_allowed` | `diag.json` |
| Release on fresh purchase | P23 | 400 | `purchase_release_wrong_status` | `state-release.json` |
| Refund on fresh purchase | P23 | 400 | `purchase_refund_wrong_status` | `state-refund.json` |
| Capture on fresh purchase | P23 | 400 | `purchase_capture_wrong_status` | `state-capture.json` |
| Tokenless charge | P23 | 400 | `invalid_recurring_token` (token check fires first) | `state-charge.json` |
| Repeat cancel (first cancel 200) | P23 | 400 | `purchase_cancel_wrong_status` | `cancel2.json` |
| Repeat capture on paid hold | P27 | 400 | `purchase_capture_wrong_status` | `cap2.json` |
| Over-capture on paid hold | P27 | 400 | field `amount`: `max_value` ("less than or equal to 100") | `cap-over.json` |
| Second full refund | P27 | 400 | `purchase_refund_not_possible` | `refund2.json` |

Sample bodies (all top-level `__all__` arrays — see Question 2):

```json
{"__all__":[{"message":"Incorrect secret_key","code":"authentication_failed"}]}
{"__all__":[{"message":"Only Purchases with `status == hold` can be captured.","code":"purchase_capture_wrong_status"}]}
{"amount":[{"message":"Ensure this value is less than or equal to 100.","code":"max_value"}]}
{"__all__":[{"message":"This Purchase was already fully refunded.","code":"purchase_refund_not_possible"}]}
```

### The guide's own 409/422 examples, tested live

P27 reached both prerequisite states via a real card-paid pre-auth hold
(Playwright + Chromium, sandbox card, `skip_capture` top-level):

- **409 case** ("capture an already-captured purchase"): capture once → 200
  `type: purchase, status: paid` (`cap1.json`); capture again → **400**
  `purchase_capture_wrong_status` (`cap2.json`). No 409.
- **422 case** ("amount exceeds the remaining capturable balance"):
  over-capture → **400** field error `max_value` (`cap-over.json`). No 422.

### The ask

Which operations actually return 403/409/422? Plus a side note: bad keys
return 401 `authentication_failed`, not the guide's `unauthorized` — is the
guide's code name stale?

## Question 2 — Is the object-shaped `__all__` envelope ever emitted?

### The spec contradiction

The OpenAPI snapshot (`chip-collect-fresh.yaml`, same in the original fetch)
shows `__all__` as an **array** in the charge operation:

- ~:301 — prose example: `"__all__": [{ "message": "Invalid or inactive
  recurring token!", "code": "invalid_recurring_token" }]`
- ~:327 — 400 response example: `__all__: [{message, code}]`

but as an **object** in the shared generic-400 response (~:1269):

```yaml
example: |
  {
    "__all__": {
      "message": "descriptive error message",
      "code": "error_code"
    }
  }
```

### What we observed

**15/15 array-valued `__all__` occurrences, zero object/string occurrences:**

- 13 top-level: `auth403`, `both`, `cancel2`, `cap2`, `diag`, `get404`,
  `noclient`, `refund2`, `state-capture`, `state-charge`, `state-refund`,
  `state-release`, `zero`.
- 2 nested at `purchase.products[0].__all__`: `p25g`, `p25h`
  (`product_subtotal_negative`, "Discount can't be larger than price *
  quantity!").

Other field errors use keyed/nested shapes (e.g. `cap-over.json`'s `amount`
list, P7's per-field arrays) — the claim is only about `__all__` values.

### Local handling

Our parser accepts object/list/string defensively and promotes only the
top-level code, so either shape parses. An earlier docs claim that object
`__all__` had been observed was corrected — the parser is unchanged, and the
object branch is unproven-by-sample, not proven-by-sample.

### The ask

Is the object form at :1269 ever emitted, or is :1269 stale?

## Question 3 — Is `Idempotency-Key` honored for purchase creation?

### What the guide says

The errors guide's `## Idempotency` section (live page + saved copy,
verbatim):

> For write operations (create, charge, capture, refund, etc.), the same
> request body sent twice with the same `Idempotency-Key` header will return
> the same result without performing the action twice. Always generate an
> `Idempotency-Key` server-side for non-idempotent operations so a network
> retry does not create duplicates.

The header appears **nowhere** in the OpenAPI snapshots or the intro page —
it is prose-only, with no header definition, no scope/expiry semantics, and no
conflict behavior.

### What we tried

- **P4:** `Idempotency-Key` replay — same key, then identical payload →
  two distinct purchases (reported 201 + 201). Artifacts `p4a`, `p4b`, `p4c`
  (initial `a`/`b` bodies differ by reference; `c` is the identical replay).
- **P4redo** (re-proven with full artifacts after review challenge): one
  shared body (`p4redo.body.json`, reference `probe-1790683448-p4redo`), one
  shared key (`p4redo.key.txt`, `probe-idempotency-redo-1790683448`), two
  POSTs → two distinct ids:
  - `p4redo-a.json`: `22c7e657-3f78-4c11-b56f-83bfeb271ce3`
  - `p4redo-c.json`: `4e5749a6-299b-4563-8287-5a7e4cbdde78`

### Scope limits

- Only **purchase creation** was replay-probed. Whether charge / capture /
  refund / other mutations honor the header is **unverified** — the ticket
  says so explicitly.
- Request headers were not retained; the redo's common key + body + distinct
  result ids are the retained proof.

### Local posture (not CHIP's answer)

Our client sends `Idempotency-Key` on resolved creates and eight mutations
and keeps a fail-closed local ledger for create dedupe; the official PHP SDK
(`Resource/PurchasesResource.php`) does not send the header at all. P4 is also
the reason our client never auto-retries mutations.

### The ask

Confirm the header is currently ignored for purchase creation, and either
honor it or fix the guide.

## Appendix A — Sendable ticket (as-is)

> Subject: Collect API — status codes, error envelope, and one doc conflict
>
> Hi CHIP team — we're integrating against Collect (sandbox-verified; 97 saved
> JSON responses). Three items we could not settle:
>
> 1. Status codes: your errors guide lists 403 `forbidden`, 409 `conflict`,
>    and 422 `unprocessable_entity`, but our recorded probe statuses include
>    only 400, 401, 404, and 405 for errors (saved JSON bodies retain the
>    codes/shapes; API status headers were not retained). The recorded 400s
>    cover validation plus state violations (a repeat cancel, and
>    release/refund/capture on a fresh purchase, return 400
>    `purchase_*_wrong_status` — the first cancel succeeds; tokenless charge
>    returns 400 `invalid_recurring_token`). Your guide names
>    capture-on-captured as a 409 case and over-capture as a 422 cause — we
>    reached both prerequisite states live (card-paid pre-auth hold) and both
>    returned 400: repeat capture gives `purchase_capture_wrong_status`,
>    over-capture gives field `max_value`; a second full refund gives 400
>    `purchase_refund_not_possible`. Which operations actually return
>    403/409/422? (Side note: bad keys return 401 `authentication_failed`,
>    not the guide's `unauthorized`.)
> 2. `__all__` shape: the spec shows an ARRAY at :301 and :327 but an OBJECT
>    at :1269. The `__all__` values in all 15 saved error envelopes were
>    arrays (13 top-level, plus 2 nested under product errors; other field
>    errors use keyed/nested shapes). Is the object form ever emitted, or is
>    :1269 stale?
> 3. Idempotency docs conflict: the errors guide says a repeated body with
>    the same `Idempotency-Key` "will return the same result without
>    performing the action twice", but our sandbox replay (identical body,
>    same key) created a second purchase. Please confirm the header is
>    currently ignored for purchase creation and either honor it or fix the
>    guide. Other mutations were not replay-probed.
>
> Settled ourselves, no action needed: client XOR `client_id` fully enforced
> both halves (`purchase_client_or_id_required` +
> `purchase_client_or_id_only`); `GET public_key/` returns a string (spec
> canonical — note the official PHP SDK cannot handle it: its client types
> `array|stdClass` while the resource expects `['public_key']`, so static
> inspection indicates a `TypeError` against live); `?preferred=` /
> `fpx_bank_code` are checkout-URL features (direct-post docs) — no rendered
> body change observed when passed to `direct_post_url`.
>
> Thanks!

## Appendix B — Dropped fourth question (card expiry, settled by probe)

The draft originally asked whether current-month card expiry is accepted:
three passages ("no earlier than current month/year" on the payment-link and
pre-auth docs pages plus spec :88) conflicted with spec :160 ("greater than
now"). P27 settled it empirically — card pay with current-month expiry 09/26
→ `paid` (visa, flow `payform`, `expiry_month: 9`; artifacts `p6card*`,
`p6preauth*`). The question was dropped; the :88/:160 prose conflict stands
as a doc nit, not a question.

P27 also closed the direct-post/card-flow residual: wrong CVC → `error` with
attempt `{code: validation_cvc_invalid}` and no top-level `error_code`
(`p6cardfail`), matching the handler's message-first object reading.

## Appendix C — Settled FYIs quoted in the ticket

These ride along in the ticket so CHIP sees what we already resolved; they
need no answer:

- **Client XOR:** both halves enforced live — neither set →
  `purchase_client_or_id_required` (`noclient.json`, P16); both set →
  `purchase_client_or_id_only` (`both.json`, P22).
- **`GET public_key/`:** returns a JSON string (PEM, `pubkey.json`, P19) —
  spec-canonical. The official PHP SDK v2.1.0 cannot handle it (concrete
  client types `array|stdClass`, resource expects `['public_key']`).
- **`?preferred=` / `fpx_bank_code`:** checkout-URL features per the
  direct-post docs, not `direct_post_url` features — passing them to
  `direct_post_url` produced no rendered-body change (byte-identical,
  `dpuget*.html`, P21).

## Appendix D — Re-verification pointers

- Audit (durable): `audits/chip-collect-spec-review-2026-09-28.md` — probe
  table (~P0–P27 dispositions), 15-envelope recount, P27 addendum, draft.
- Probes (ephemeral `/tmp`): `/tmp/panel-chip/probes/` — 217 files; recount
  `__all__` with `rg -l '__all__' --glob '*.json' | sort`; error envelopes
  are the small (<1KB) top-level-`__all__` files plus `p25g/h.json`.
- Saved official docs (ephemeral `/tmp`): `/tmp/chip-docs/` — OpenAPI
  snapshots, `chip-collect_overview_errors.md`, direct-post pages.
- Live pages: `/chip-collect/overview/errors` (Q1+Q3),
  `/chip-collect/overview/online-purchases/pre-auth` (expiry nit context),
  OpenAPI `chip-collect.yaml` (Q2 line cites — re-check line numbers, they
  drift between snapshots).
- Draft history: 4 questions → 3 (expiry dropped after P27; renumbered former
  Q3→Q2, Q4→Q3). Review loops through Sol R4 APPROVE; no open issues beyond
  sending.

## Appendix E — When the answer arrives

Likely outcomes and what they touch:

- **403/409/422 real cases named:** extend our error taxonomy/docs; add fake
  + handler coverage for the newly known codes.
- **:1269 confirmed stale (or live):** one-line docblock/docs touch; parser
  already handles both.
- **Idempotency honored / guide fixed:** if honored, our ledger becomes
  defense-in-depth and mutation replay probes should be run; if the guide is
  fixed to "not supported on create", our docs already say that — just cite
  the answer.
