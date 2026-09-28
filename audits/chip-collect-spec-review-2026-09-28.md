# CHIP Collect Spec-Conformance Review — OPEN (2026-09-28)

Cross-checked `packages/chip` (client, builder, enums, validation, docs)
against the live official spec: `https://docs.chip-in.asia/openapi/chip-collect.yaml`
(OpenAPI 3.1, 184KB, fetched 2026-09-28) plus the Collect intro and conventions pages.

Status: findings logged, fixes NOT applied. Revisit together with the
3 open product decisions listed at the end of the docs-audit close-out.

## Verified correct

- All 33 spec endpoints covered with correct methods and trailing slashes:
  purchases CRUD + 8 actions (`cancel`, `refund`, `charge`, `capture`,
  `release`, `mark_as_paid`, `resend_invoice`, `delete_recurring_token`),
  clients + recurring tokens, webhooks CRUD, `public_key`,
  `account/json/balance`, `account/json/turnover`, statements + cancel,
  `payment_methods`.
- `Authorization: Bearer <secret_key>` on Collect calls.
- Prices in minor units; quantity sent as string; per-product `discount`
  only when > 0; `tax_percent` only when > 0.
- Client XOR `client_id` with required email enforced client-side before POST.
- All 26 spec webhook events in `WebhookEventType`; purchase statuses mapped.
- Webhook verification matches spec exactly: raw body buffer, base64,
  RSA PKCS#1 v1.5 + SHA256 over `X-Signature`, with split keys
  (company-wide `GET /public_key/` for success callbacks,
  `Webhook.public_key` for webhooks).
- `skip_capture`/preauth, `force_recurring`, 3 redirect URLs, `due`/`due_strict`,
  `send_receipt`, `reference`, `notes`, `metadata`.
- `mark_as_paid(paid_on)`, `refund(amount)`, `charge(token)`,
  `capture(amount)` params match spec bodies.
- `?preferred=` checkout deep-linking is spec'd; FPX bank enum cases exist.
- `clientId()` correctly unsets the `client` payload (spec: only one allowed).

## Q2 (from docs-audit close-out) — ANSWERED

`tax_percent` = percent of tax ADDED to the product price, computed
server-side. `subtotal_override`, `total_tax_override`, and
`total_discount_override` are explicitly **visual-only** ("setting it won't
impact `total`"); only `total_override` changes the charge. Our
`total_override` mapping is therefore correct and no overcharge path exists
through these fields.

## Bugs (fix on revisit)

1. **Double tax at line level** — `CartCheckoutLineItem` passes item
   `tax_percent` through while the item price already includes tax
   conditions (`MoneyTrait::getRawPrice()` applies ALL item conditions).
   CHIP adds the percent a second time. Charge is safe (`total_override`
   governs); invoice line display inflates. Fix: report `0.0` in the wrapper.
   - `packages/cart/src/Models/CartCheckoutLineItem.php`
2. **`purchase.total` sent but absent from spec** — `fromCheckoutable()`
   sets `$data['purchase']['total']`; `PurchaseDetailsBody` has no `total`
   field (likely ignored server-side). Strip it; keep `total_override`.
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~line 252)
3. **`idempotency_key` sent in body but absent from spec** — the local
   ledger + cache is the real idempotency mechanism; the wire field is
   unknown to CHIP. Strip from the POST payload (keep local behavior).
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~line 609)
4. **`discount()` is display-only** — sets `total_discount_override`, which
   per spec won't impact `total`. A caller expecting a cheaper purchase gets
   an invoice-display change only. Document loudly on the method + usage docs.
   - `packages/chip/src/Builders/PurchaseBuilder.php` (~line 564)
5. **`ProductData::toArray()` emits `"category": null`** — spec types
   `category` as non-nullable `string`. The `addProductObject()` path (no
   test coverage) could 400 on strict validation. Omit nulls.
   - `packages/chip/src/Data/ProductData.php` (~line 152)
6. **Docs "FPX Direct Post" mislabels checkout deep-linking** — real Direct
   Post = card form posted to `direct_post_url` (unsupported in SDK; we only
   read the URL from responses). The section shows `?preferred=` deep-links,
   appends `?preferred=` to `direct_post_url` when present (wrong endpoint
   for that param), and uses `fpx_bank_code`, which appears nowhere in the
   spec. Rename, always build on `checkout_url`, verify the bank-code param
   against CHIP's FPX prose guide or drop it.
   - `packages/chip/docs/07-chip-collect.md` (~line 68)

## Docs gaps (improvements, not bugs)

- `09-webhooks.md`: no retry-protocol specifics — spec says 8 additional
  attempts, 36h window, at-least-once duplicates possible, sequential per
  source object.
- No test-card docs — spec: `4444 3333 2222 1111`, CVC `123`, future expiry,
  any Latin name; anything else fails in test mode.
- No `?active=` param mention (spec'd pre-select alternative to `?preferred=`).

## Coverage gaps (not bugs — no builder support)

`payment_method_whitelist`, `tags`, `language`, `debt`, `timezone`,
`issued`, `email_message`, `request_client_details`, client company/cc/bcc
fields, per-product `total_price_override`, 256-char name/category validation
(server 400s instead). `tax_percent` sent as JSON number vs spec'd string
type — almost certainly tolerated; noted only.

## Residual external unknown

How CHIP's server combines conditioned line prices with `tax_percent` for
invoice display is only documented as "added to the price" — enough to fix
finding 1, but display-level behavior past that needs a sandbox purchase to
confirm. No repo-side evidence beyond the spec sentence exists.
