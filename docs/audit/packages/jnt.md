# Audit: `jnt` (AIArmada\Jnt)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** J&T Express Malaysia carrier adapter — order creation, tracking, cancellation, waybill printing, webhooks.

**Surface:** gateway

---

## Findings

### Medium
1. **`JntWebhookLog` lacks `$fillable`/`$guarded`** — Extends Spatie's `WebhookCall` which defines neither `$fillable` nor `$guarded`. Model inherits Laravel's default `$guarded = ['*']` for `creating`/`saving`, but Spatie's `WebhookCall` model may have different behavior. Risk of mass-assignment on webhook payload data stored in shared `webhook_calls` table.

### Low
2. **Legacy command stubs not cleaned up** — 4 flat-namespace command stubs (`OrderCreateCommand.php`, `OrderPrintCommand.php`, `OrderTrackCommand.php`, `OrderCancelCommand.php`) live at `src/Console/Commands/` alongside canonical versions in `src/Console/Commands/Order/`. Stubs are not registered in the service provider but are shipped dead code.
3. **No `booted()` application-level cascades** — Models have related records (order → items, parcels, tracking events), but no `deleting` events to cascade clean up. Relies on DB-level or manual cleanup.
4. **Hardcoded test credentials in config** — `config/jnt.php` defaults to J&T's public sandbox credentials when `JNT_ENVIRONMENT=testing`. These are test credentials (documented), but shipping them in config is unusual — env-only would be safer.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ All except WebhookLog | 4/5 models use `$fillable`; `JntWebhookLog` extends Spatie model without it |
| Exception hierarchy | ✅ 4 custom classes | `JntException` base → `JntApiException`, `JntNetworkException`, `JntValidationException` |
| PHP enums | ✅ 9 enums | `OrderStatus`, `ExpressType`, `ServiceType`, `PaymentType`, `GoodsType`, `CancellationReason`, `JntEnvironment`, `WebhookEvent`, `TrackingCacheTtl` |
| Owner scoping | ✅ All 5 models | `HasOwner` + `HasOwnerScopeConfig` |
| Immutable dates | ✅ `CarbonImmutable` | All datetime casts |
| Actions | ✅ 4 (lorisleiva/laravel-actions) | Create, Cancel, Track, GenerateLabel — all with `AsAction` trait |
| Services | ✅ 3 | `JntExpressService` (orchestrator), `JntSignatureService` (MD5), `JntWebhookProcessorService` (parser) |
| HTTP client | ✅ Saloon connector | Retry (3×/500ms), timeout (30s), MD5 signature, config-driven base URI |
| Data objects | ✅ 8 Spatie Data classes | SnakeCaseMapper, MapInputName for API field translation |
| Webhooks | ✅ Full Spatie pipeline | Profile, config, signature validator, queued processor job |
| Events | ✅ 9 events | Order lifecycle + tracking + webhook lifecycle |
| Tests | ✅ ~25 in-package | Actions, services, models, controllers, commands, webhooks, data objects, client |
| Docs | ✅ 12 files | Exceptionally well-documented — overview, install, config, usage, troubleshooting, advanced, API reference, batch ops, cart integration, error handling, testing credentials, webhooks |
| Builder pattern | ✅ Fluent `OrderBuilder` | Domain-specific fluent API for request construction |
| Shipping driver | ✅ `JntShippingDriver` | Integrates with commerce-support shipping contract |
| Cart integration | ✅ Rate provider, validator, modifier | Full cart checkout integration |

---

## Summary

Well-crafted carrier adapter for J&T Express Malaysia. Uses Saloon HTTP connector with proper retry/timeout/signing, Spatie Data for typed DTOs, laravel-actions for single-responsibility operations, fluent builder for request construction, and Spatie webhook client for inbound push processing.

Exception hierarchy is proper (4 classes with `JntException` base). 9 PHP enums cover the domain. 8 Spatie Data classes with `SnakeCaseMapper` for API field translation. 3 services handle orchestration, signing, and webhook processing. 25 in-package tests cover actions, services, controllers, webhooks, commands, data objects, and the HTTP client. 12 documentation files make this the best-documented package in the repo.

Shipping and cart integrations register conditionally. Health check integrates with `spatie/laravel-health`. Webhook pipeline uses Spatie's proven pattern with a custom signature validator for J&T's MD5-digest scheme.

**Verdict:** Ready. Exception hierarchy, tests in-package, excellent docs, proper integration patterns. Minor issues: `JntWebhookLog` fillable gap, legacy stubs, no `booted()` cascades.
