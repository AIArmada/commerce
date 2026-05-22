---
title: CHIP Collect Audit - 2026-05-22
---

# CHIP Collect Audit - 2026-05-22

## Scope

This audit compares `packages/chip` against:

- the local CHIP Collect documentation mirror at `packages/chip/docs/chip-collect-API-2`
- the online CHIP Collect documentation starting at `https://docs.chip-in.asia/chip-collect/overview/introduction`

Covered areas:

- Collect overview and authentication
- callbacks and webhook verification
- purchase endpoints
- pre-authorization and subscription flows
- direct-post guidance

Out of scope:

- Chip Send, except where package docs or shared webhook code overlap incidentally
- direct live API calls against the vendor service unless explicitly requested

## Notes

- The user originally referenced `/packages/aiarmada/chip`, but the package present in this repository is `packages/chip`.
- The local offline documentation bundle previously used during audit has been copied into the repository at `packages/chip/docs/chip-collect-API-2` for easier reference.
- Unless stated otherwise, documentation references in this file are relative to `packages/chip/docs/chip-collect-API-2`.
- Every fix in this tracker must be re-verified against both the local docs mirror and the online docs entry point at `https://docs.chip-in.asia/chip-collect/overview/introduction` before it can be promoted to `Verified`.
- This document is intended as a fix tracker, so each finding includes a status and suggested verification steps.
- Status vocabulary:
  - `Open` = identified, not yet addressed
  - `In Progress` = actively being fixed
  - `Fixed` = implementation changed
  - `Verified` = implementation changed, targeted checks passed, and the change was re-checked against both local and online CHIP Collect documentation
  - `Needs API confirmation` = docs and code disagree, but the live contract should be confirmed before finalizing behavior

## Reference sources for all future fixes

Before changing implementation for any finding below, use both of these references together:

1. Local docs mirror: `packages/chip/docs/chip-collect-API-2`
2. Online docs root: `https://docs.chip-in.asia/chip-collect/overview/introduction`

When the local and online documentation disagree, do not silently pick one. Record the discrepancy in this tracker and use the stricter or better-supported interpretation until the contract is confirmed.

## Local mirror coverage gaps

The repository mirror at `packages/chip/docs/chip-collect-API-2` currently covers 23 CHIP Collect pages. After normalizing the mirror's `purchases/*.mdx` files to the live docs' `api-reference/purchases/*.md` path scheme, the live `llms.txt` index still shows 24 CHIP Collect pages that are not mirrored locally.

### Important note on path shape

- Local mirror purchase API pages live under `packages/chip/docs/chip-collect-API-2/purchases/*.mdx`
- Live docs expose the same pages under `chip-collect/api-reference/purchases/*.md`

That path mismatch is only organizational; the purchase API pages themselves are mirrored. The missing pages are the groups below.

### Missing API reference groups

- Account
  - `api-reference/account/balance`
  - `api-reference/account/turnover`
- Clients
  - `api-reference/clients/create`
  - `api-reference/clients/delete`
  - `api-reference/clients/delete-recurring-tokens`
  - `api-reference/clients/list`
  - `api-reference/clients/list-recurring-tokens`
  - `api-reference/clients/partial-update`
  - `api-reference/clients/retrieve`
  - `api-reference/clients/retrieve-recurring-tokens`
  - `api-reference/clients/update`
- Company statements
  - `api-reference/company-statements/cancel`
  - `api-reference/company-statements/list`
  - `api-reference/company-statements/retrieve`
  - `api-reference/company-statements/schedule`
- Payment methods
  - `api-reference/payment-methods/list`
- Public key
  - `api-reference/public-key/retrieve`
- Webhooks
  - `api-reference/webhooks/create`
  - `api-reference/webhooks/delete`
  - `api-reference/webhooks/list`
  - `api-reference/webhooks/partially-update-a-webhook-by-id`
  - `api-reference/webhooks/retrieve`
  - `api-reference/webhooks/update`

### Missing overview page

- `overview/vibe-coding-guide`

### Why this matters

- The missing `clients/*` pages matter for recurring-token verification work because the client-level token endpoints are not available in the local mirror.
- The missing `public-key/*` and `webhooks/*` pages matter for webhook verification and webhook-management work.
- The missing `payment-methods/*` and `account/*` pages matter for payment-method lookup and reporting checks.

## Executive summary

Core Collect purchase coverage is generally strong, but there is meaningful drift around webhook verification, callback modeling, and one purchase endpoint shape.

| Severity | Finding | Status |
| --- | --- | --- |
| High | Built-in webhook verification defaults to the company key instead of the documented per-webhook key | Verified |
| High | Recurring-token deletion endpoint does not match the offline purchase docs | Verified |
| Medium | Refund flows are modeled as `PurchaseData` while the docs say refund completion returns a `Payment` | Verified |
| Medium | Universal webhook handler rewrites `event_type` from `status`, losing documented lifecycle events | Verified |
| Low | Legacy HMAC-style signature validator contradicts the documented RSA model | Verified |
| Low | FPX direct-post bank enum does not fully cover B2B1 bank codes from the docs | Verified |

## Confirmed aligned areas

These areas looked good during the audit and should not need speculative changes:

- Collect base URL and Bearer auth in `packages/chip/config/chip.php` and `packages/chip/src/Clients/ChipCollectClient.php`
- broad purchase endpoint coverage in `packages/chip/src/Services/Collect/PurchasesApi.php`
- builder support for `success_callback`, `skip_capture`, `force_recurring`, and `payment_method_whitelist`
- active signature verification path uses RSA + SHA-256 with `X-Signature`

## Findings to track

### High: Built-in webhook verification defaults to the company key

**Status:** Verified

**Why it matters**

The offline docs distinguish between:

- success callbacks signed with the company key from `GET /public_key/`
- webhook deliveries signed with `Webhook.public_key`

The package supports webhook-specific key lookup in `WebhookService::getPublicKey(?string $webhookId = null)`, but the default runtime path never supplies a webhook ID. That means the built-in verifier falls back to the company key.

**Code references**

- `packages/chip/src/ChipServiceProvider.php`
- `packages/chip/src/Webhooks/ChipSpatieSignatureValidator.php`
- `packages/chip/src/Services/WebhookService.php`
- `packages/chip/src/Http/Controllers/WebhookController.php`
- `packages/chip/routes/webhooks.php`

**Documentation references**

- `chip-collect API 2/overview/authentication.mdx`
- `chip-collect API 2/overview/callbacks.mdx`

**Fix direction**

- [x] Confirm whether the built-in route is intended for success callbacks, registered webhooks, or both
- [x] If it handles registered webhooks, pass enough context to select `Webhook.public_key`
- [x] Keep company-key verification only for success-callback flows
- [x] Update package docs so key selection rules are explicit

**Verification**

- [x] Add a test covering company-key success callback verification
- [x] Add a test covering webhook-specific public key verification
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Services/WebhookServiceTest.php`
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Http/HttpTest.php`
- [x] Run `./vendor/bin/phpstan analyse packages/chip/src --level=6`

**Implementation notes**

- `WebhookService::verifySignature()` now tries multiple candidate public keys for incoming requests.
- Configured webhook public keys are attempted first for registered webhook deliveries.
- The company public key remains a fallback for success-callback verification.
- If a webhook identifier is supplied in headers or payload, the service can still resolve a specific webhook key directly.
- Local docs mirror and online docs both confirm the company-key vs `Webhook.public_key` split.

### High: Recurring-token deletion endpoint differs from the offline docs

**Status:** Verified

**Why it matters**

The offline purchase docs show recurring-token deletion as:

- `POST /purchases/{id}/delete_recurring_token/`

The package currently calls:

- `DELETE /purchases/{id}/recurring_token/`

That is a direct contract mismatch between code and the attached documentation.

**Code references**

- `packages/chip/src/Services/Collect/PurchasesApi.php`
- `packages/chip/src/Services/ChipCollectService.php`
- `tests/src/Chip/Unit/Services/ChipCollectServiceTest.php`

**Documentation reference**

- `chip-collect API 2/purchases/delete-recurring-token.mdx`

**Fix direction**

- [x] Confirm the actual CHIP endpoint and HTTP method from the local and online documentation
- [x] Align the package implementation to the confirmed contract
- [x] Update or add tests for the final endpoint shape
- [x] Update internal package docs to match the corrected return type

**Verification**

- [x] Confirm the local docs mirror and online docs both specify `POST /purchases/{id}/delete_recurring_token/`
- [x] Add a request-shape unit test for the confirmed endpoint
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Services/ChipCollectServiceTest.php`
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Services/Collect/PurchasesApiTest.php`
- [x] Run `./vendor/bin/phpstan analyse packages/chip/src --level=6`

**Implementation notes**

- `PurchasesApi::deleteRecurringToken()` now uses `POST /purchases/{id}/delete_recurring_token/`.
- The method now returns `PurchaseData`, matching the documented purchase response payload.
- `ChipCollectService::deleteRecurringToken()` and the `Chip` facade annotation were updated to reflect the returned purchase snapshot.
- `packages/chip/docs/api-reference.md` was updated to keep the package docs aligned.

### Medium: Refund completion is modeled as `PurchaseData` instead of `PaymentData`

**Status:** Verified

**Why it matters**

The offline docs state that refund completion yields a `Payment` object and that `payment.refunded` delivers a `Payment`. The package currently models refund responses and refund webhook events around `PurchaseData`.

**Code references**

- `packages/chip/src/Services/Collect/PurchasesApi.php`
- `packages/chip/src/Services/ChipCollectService.php`
- `packages/chip/src/Gateways/ChipGateway.php`
- `packages/chip/src/Services/WebhookEventDispatcher.php`
- `packages/chip/src/Events/PaymentRefunded.php`
- `packages/chip/src/Data/PaymentData.php`

**Documentation reference**

- `chip-collect API 2/purchases/refund.mdx`

**Fix direction**

- [x] Confirm the actual refund response payload shape from the local and online CHIP docs
- [x] Switch completed refund responses to `PaymentData` while keeping pending refunds as `PurchaseData`
- [x] Revisit `PaymentRefunded` event typing, webhook enrichment, and gateway refund wrappers
- [x] Update package docs and examples to match the confirmed contract

**Verification**

- [x] Confirm the local refund docs say successful refunds return a Payment object and pending refunds return a Purchase with `status = pending_refund`
- [x] Confirm the online refund docs say successful refunds return a Payment object and pending refunds return a Purchase with `status = pending_refund`
- [x] Run refund-focused regression tests under `tests/src/Chip`
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip`
- [x] Run `./vendor/bin/phpstan analyse packages/chip/src --level=6`

**Implementation notes**

- `PurchasesApi::refund()` and `ChipCollectService::refundPurchase()` now return `PaymentData` for completed refunds and `PurchaseData` for pending refunds.
- `PaymentData` now understands the documented top-level refund Payment resource, including `related_to`, client details, and reference fields.
- `ChipGateway::refundPayment()` now converts completed refund payments back into the related purchase intent by reloading the purchase, while pending refunds continue to use the returned purchase snapshot directly.
- `PaymentRefunded`, `WebhookEventDispatcher`, `WebhookReceived`, `EnrichedWebhookPayload`, and `StoreWebhookData` now treat `payment.refunded` as a payment-shaped payload instead of forcing it through `PurchaseData`.
- Package docs were updated so `refundPurchase()` and refund webhook examples match the CHIP contract.

### Medium: Universal webhook handler rewrites documented `event_type` values

**Status:** Verified

**Why it matters**

`ChipWebhookHandler::getEventType()` infers synthetic event names from `status` instead of preserving the payload's explicit `event_type`. This loses details such as:

- `purchase.pending_capture`
- `purchase.captured`
- `purchase.pending_charge`
- `purchase.payment_failure`

The built-in event dispatcher already understands real CHIP event types, so this mismatch is mostly a problem for consumers using the universal gateway adapter.

**Code reference**

- `packages/chip/src/Gateways/ChipWebhookHandler.php`

**Documentation references**

- `chip-collect API 2/overview/callbacks.mdx`
- `chip-collect API 2/purchases/create.mdx`
- `chip-collect API 2/purchases/capture.mdx`
- `chip-collect API 2/purchases/charge.mdx`

**Fix direction**

- [x] Prefer payload `event_type` when present
- [x] Fall back to status inference only for legacy or malformed payloads
- [x] Add tests for event preservation across purchase lifecycle callbacks

**Verification**

- [x] Confirm the local and online callbacks docs state that callback payloads include `event_type`
- [x] Add a handler test for `purchase.pending_capture`
- [x] Add a handler test for `purchase.payment_failure`
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Gateways/ChipWebhookHandlerTest.php`
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/FacadesAndGatewaysTest.php`
- [x] Run `./vendor/bin/phpstan analyse packages/chip/src --level=6`

**Implementation notes**

- `ChipWebhookHandler::getEventType()` now returns the payload's explicit `event_type` when CHIP provides one.
- Status-based inference remains only as a fallback for legacy or malformed payloads that omit `event_type`.
- This preserves documented lifecycle events such as `purchase.pending_capture` and `purchase.payment_failure` in the universal gateway adapter.

### Low: Legacy HMAC-style validator conflicts with the documented signature model

**Status:** Verified

**Why it matters**

`ChipSignatureValidator` and its tests describe CHIP webhook verification like an HMAC/shared-secret integration, while the active verification path uses RSA public-key verification. The class appears unused, but it is misleading and could cause future wiring mistakes.

**Documentation reference**

- `chip-collect API 2/overview/authentication.mdx`

**Fix direction**

- [x] Confirm the class is not the active runtime validator
- [x] Remove the compatibility alias because the package has no downstream consumers in this repository
- [x] Keep the RSA/public-key validation path as the only CHIP webhook validator under test

**Verification**

- [x] Confirm the local docs mirror states callbacks use RSA public-key verification
- [x] Confirm the online docs state callbacks use RSA public-key verification and `Webhook.public_key` / `GET /public_key/`
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Services/WebhookServiceTest.php tests/src/Chip/Feature/WebhookProcessingTest.php`
- [x] Run `./vendor/bin/phpstan analyse packages/chip/src --level=6`

**Implementation notes**

- The deprecated `ChipSignatureValidator` compatibility alias was removed entirely because the package has no downstream consumers in this repository.
- Runtime webhook configuration already uses `ChipSpatieSignatureValidator`, so removing the alias does not change the active webhook path.
- The dedicated compatibility test file was removed with it, leaving the active RSA/public-key validator as the only webhook verification path under test.

### Low: FPX direct-post helpers are incomplete for B2B1 bank codes

**Status:** Verified

**Why it matters**

The docs define additional B2B1-only bank codes, but `FpxBank` currently only covers the B2C set while `FpxType` already exposes `fpx_b2b1`.

**Code references**

- `packages/chip/src/Enums/FpxBank.php`
- `packages/chip/src/Enums/FpxType.php`

**Documentation reference**

- `chip-collect API 2/overview/direct-post/fpx.mdx`

**Known missing examples from docs**

- `ABB0235`
- `ABMB0213`
- `AGRO02`
- `AMBB0208`
- `BMMB0342`
- `BNP003`
- `CIT0218`
- `DBB0199`
- `PBB0234`
- `SCB0215`
- `UOB0228`

**Fix direction**

- [x] Expand `FpxBank` coverage to include the missing B2B1 bank codes
- [x] Add tests for B2B1 code lookup and labeling
- [x] Update helper docs with B2B1 examples

**Verification**

- [x] Confirm the local docs mirror and online docs both list the B2B1 bank-code set used for direct post
- [x] Record the local-vs-online discrepancy where the online docs include `MBSB001` for B2C while the local mirror does not
- [x] Run `./vendor/bin/pest --parallel tests/src/Chip/Unit/Enums/FpxBankTest.php`
- [x] Run `./vendor/bin/phpstan analyse packages/chip/src --level=6`

**Implementation notes**

- `FpxBank` now includes the missing B2B1 bank codes referenced by the CHIP direct-post docs, including `ABB0235`, `ABMB0213`, `AGRO02`, `AMBB0208`, `BMMB0342`, `BNP003`, `CIT0218`, `DBB0199`, `PBB0234`, `SCB0215`, and `UOB0228`.
- The helper test suite now verifies the newly-added codes, labels, and array output.
- `packages/chip/docs/chip-collect.md` now includes a direct-post example using `FpxType` and `FpxBank` for B2B1 flows.
- The online docs include `MBSB001` (`MBSB Bank`) in the B2C list, while the local mirror does not. The package now includes that code and this discrepancy is recorded here rather than silently ignored.

## Suggested fix order

1. Fix webhook key selection
2. Confirm and align recurring-token deletion endpoint
3. Confirm refund payload shape and align refund modeling
4. Preserve explicit `event_type` in the universal webhook handler
5. Remove or clarify legacy HMAC validator
6. Expand direct-post helper coverage for FPX B2B1

## Package docs likely needing follow-up

If the implementation changes, these docs should be reviewed together so package documentation stays aligned:

- `packages/chip/docs/webhooks.md`
- `packages/chip/docs/chip-collect.md`
- `packages/chip/docs/api-reference.md`
- `packages/chip/docs/03-configuration.md`

## Post-review follow-up fixes

- Payment-shaped `payment.refunded` webhooks are now persisted by the remote CHIP payment ID, so multiple same-amount refunds on one purchase no longer collapse into a single local payment row.
- The generic `WebhookReceived` event now carries `PaymentData` in both the queued webhook processor and the testing simulator, keeping runtime and test dispatch behavior aligned.
- Refund regression coverage now proves both documented refund branches: completed refunds returning `PaymentData` and delayed refunds returning a `PurchaseData` with `status = pending_refund`.
- Gateway-level refund tests now verify that payment-shaped refund responses reload the related purchase before returning a `ChipPaymentIntent`.
- Stale planning/docs references to the removed `ChipSignatureValidator` alias were updated to either `ChipSpatieSignatureValidator` or a generic custom-validator example.
- A focused follow-up audit cleaned the remaining refund-adjacent dispatcher and handler tests so `payment.refunded` fixtures now use payment-shaped payloads instead of pretending to be purchases.
- Refund purchase-state synchronization now runs on the live queued webhook path too, via a shared action used by both `WebhookEventDispatcher` and `PurchaseRefundedHandler`.
- Local purchases now distinguish partial vs full refunds by comparing cumulative persisted refund payments against the purchase total, updating `status`, `refund_amount_minor`, and `refundable_amount` together.
- Docs integration now deduplicates refund credit notes by CHIP refund payment ID instead of blocking all later refunds for the same purchase.
- The shared Commerce Support webhook guide was rewritten to stop presenting CHIP as an HMAC/shared-secret integration and now explicitly distinguishes generic HMAC validators from CHIP-style public-key verification.
- Refund-state synchronization now also accumulates correctly when `chip.webhooks.store_webhooks` is disabled by using persisted purchase refund totals as a fallback ledger.
- The universal gateway adapter now maps additional documented CHIP statuses such as `settled`, `cleared`, `released`, `chargeback`, and `overdue` to more accurate `PaymentStatus` values instead of collapsing them into `pending`.
- `WebhookFactory::failed()` now emits the real CHIP event type `purchase.payment_failure`, and `WebhookSimulator::dispatch()` now routes through `WebhookEventDispatcher` so test helpers exercise the same refund-sync side effects as runtime processing.
- `WebhookSimulator::toRequest()` now preserves JSON request headers correctly, so `ChipWebhookProfile` can read `event_type` from simulated requests, and `WebhookSimulator::forEvent()` / `fakeEvents()` now cover the pending and recurring purchase events tracked by the package.
- The retry path now restores owner context before mutating stored webhook records and replays enum-backed CHIP events through `WebhookReceived` plus `WebhookEventDispatcher`, so retries match live processing more closely under multi-tenant owner scoping.
- Malformed `payment.refunded` payloads now fail closed in both the dispatcher and the legacy refund handler instead of synthesizing zero-value `PaymentData` objects from missing payment details.
- The CHIP Spatie integration blueprints and task docs were refreshed to use top-level CHIP payloads, public-key verification, and the current event names such as `purchase.paid`, `purchase.pending_refund`, and `payment.refunded`.
- Payment helper constructors and generic webhook hydration now also fail closed for malformed `payment.refunded` payloads, so `WebhookReceived::fromPayload()`, `PaymentRefunded::fromPayload()`, and `WebhookEventDispatcher::extractPayment()` no longer synthesize zero-value refund payments from missing money fields.
- `chip:retry-webhooks --limit` is now enforced as a global cap across owner scopes instead of applying the same limit separately per owner during multi-tenant retry sweeps.
- The Spatie payment integration blueprint now marks its webhook-state analysis as historical baseline context so it no longer contradicts the completed rollout recorded in `docs/spatie-integration/PROGRESS.md`.
- Local analytics now treat `partially_refunded` purchases as successful revenue sources with separate refund amounts, so dashboard revenue, transaction counts, payment-method breakdowns, and revenue trends no longer drop partial refunds out of reporting.
- The retry command now enters explicit global owner context when owner mode is enabled but there are no stored webhook owners yet, preventing `chip:retry-webhooks` from throwing on a fresh install.
- `WebhookSimulator::dispatch()` now mirrors the HTTP webhook path more closely by carrying the active owner tuple into direct-dispatch payloads when owner mode is enabled, so owner-aware refund listeners behave consistently in tests.
- `WebhookData::from()` now treats payment-shaped `payment.refunded` deliveries as real incoming webhook payloads, so simulator helpers no longer drop raw refund payloads when converting them into `WebhookData` objects.

## Verification checklist for the eventual fix set

- [x] Re-check each fix against `packages/chip/docs/chip-collect-API-2`
- [x] Re-check each fix against `https://docs.chip-in.asia/chip-collect/overview/introduction`
- [x] Record any local-vs-online documentation discrepancies before finalizing behavior
- [x] `./vendor/bin/pest --parallel tests/src/Chip`
- [x] `./vendor/bin/phpstan analyse packages/chip/src --level=6`
- [x] Re-read package docs for webhook, refund, and recurring-token examples
- [x] Confirm no stale tests still encode the old contract assumptions
