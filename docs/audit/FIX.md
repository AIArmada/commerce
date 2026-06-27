# Verified Fix Plan For `FINDINGS.md`

## Metadata

| Field | Value |
|---|---|
| Verification date | 2026-06-27 |
| Target baseline | Current working tree, not the historical audit commit |
| Current HEAD | `4af108305` |
| Source file | `docs/audit/FINDINGS.md` |
| Historical audit commit in source | `7d1dc95fa` |
| Planned mutation for this pass | Create this file only |
| Existing worktree changes preserved | `ANCHORED_SUMMARY.md` deleted; `docs/audit/` untracked |

## Verification Rules Applied

- Monorepo rules override generic Laravel guidance. In particular: no database constraints or cascades, owner scoping must be server-enforced, and package docs are canonical.
- Each package claim was checked against the current working tree using package context files, canonical package docs, and scoped inspection commands.
- Mixed claims were split in the verdict where needed. Example: `Schema::hasColumn()` use is true; the 5-10ms runtime estimate is unverified.
- Test-coverage claims were checked against root monorepo tests under `tests/src`, not only package-local `tests/` directories.
- Roadmap items are not automatically fixes. They are carried forward only when backed by a true or true portion of a verified finding.

## Verdict Summary

| Verdict | Count |
|---|---:|
| True | 82 |
| Partially true | 39 |
| False | 27 |
| Unverified runtime claim | 4 |
| Derived from true finding | 26 |
| Requires product approval | 2 |

The source mixes explicit severities, section-level severities, and roadmap phases. The normalized severity used for fixing is the remediation bucket below.

| Normalized severity / fix bucket | Planned fix items |
|---|---:|
| P0: verification and CI blockers | 4 |
| P1: critical security, tenant isolation, payment/webhook behavior | 7 |
| P2: behavioral bugs, state integrity, performance | 6 |
| P3: config, composer, docs, navigation, integration gaps | 7 |
| P4: architecture and consistency improvements | 6 |

## Claim Ledger

| ID | Package | Claim | Verdict | Evidence | Fix needed | Test / verification required |
|---|---|---|---|---|---|---|
| B1 | `promotions` | `MarkPromotionAsUsedOnOrderPlaced` increments `times_used`, but the schema/model use `usage_count`. | True | `packages/promotions/src/Listeners/MarkPromotionAsUsedOnOrderPlaced.php:28`; `packages/promotions/database/migrations/2000_12_01_000001_create_promotions_table.php:31`; `Promotion.php` casts/scopes reference `usage_count`. | Change increment to `usage_count`. | Add listener regression test; run `./vendor/bin/pest --parallel tests/src/Promotions`. |
| B2 | `affiliate-network` | Archive command filters `status = active`, but enum values are `draft`, `published`, `archived`; update uses raw `archived`. | True | `packages/affiliate-network/src/Console/Commands/ArchiveExpiredOffersCommand.php:36,43`; `packages/affiliate-network/src/Enums/OfferStatus.php`. | Use `OfferStatus::Published` and `OfferStatus::Archived`. | Command test for expired published offer archived and draft untouched. |
| B3 | `chip` | Webhook/payment events default missing `is_test` to `true`. | True | `packages/chip/src/Events/WebhookReceived.php:310`; `packages/chip/src/Events/PaymentRefunded.php:87`. | Default event-level test flag to `false`; review related DTO defaults separately. | Event tests for missing `is_test` and explicit test payload. |
| B4 | `chip` | `Webhook.php` has public `$guarded = []`. | True | `packages/chip/src/Models/Webhook.php:54`. | Replace with protected explicit `$fillable`; keep Spatie webhook behavior and `name` scoping intact. | Mass-assignment regression test for protected attributes. |
| B5 | `cashier` | Stripe and CHIP retrieval methods silently swallow errors and return `null`. | Partially true | Stripe catches return `null` without logging at `StripeGateway.php:165,179,193,207`; CHIP catches log warnings at `ChipGateway.php:168,192,211,231`; webhook verification catches are also present at `StripeGateway.php:339` and `ChipGateway.php:377`. | Fix Stripe silent retrievals; for both gateways, separate not-found from transport/auth/rate-limit failures with typed exceptions or structured result objects. | Gateway tests simulating 404, auth failure, rate limit, and network exception. |
| B6a | `cashier` | `OwnerScopedQuery` calls `Schema::hasColumn()` on owner-scoped queries and does not cache the result. | True | `packages/cashier/src/Support/OwnerScopedQuery.php:68-73`. | Cache by connection/table/column using long-lived-worker-safe storage or injected schema cache. | Unit test proving repeated calls do not hit schema repeatedly; PHPStan on `packages/cashier/src`. |
| B6b | `cashier` | The schema lookup costs about 5-10ms per query. | Unverified runtime claim | No benchmark in current tree; only the schema call itself is statically provable. | Do not optimize based on the numeric estimate; benchmark if needed. | Optional benchmark before/after caching. |
| B7 | `affiliates` | `FraudDetectionService` will crash by dispatching click-only/conversion-only rules to the wrong method. | Partially true | `FraudDetectionService.php:30-33` adds all rules to both arrays, but current `Contracts/FraudRule.php` requires both `analyzeClick()` and `analyzeConversion()` and all current rules implement both. No `AnalyzesClickFraud` or `AnalyzesConversionFraud` contracts exist. | No crash fix required for current rules; only introduce split interfaces if product wants rule-specific capabilities. | Add tests if split contracts are introduced. |
| B8 | `authz` | `Authz::clearCache()` is an empty public API. | True | `packages/authz/src/Authz.php:86-89`; `flushPermissionCache()` at `91-100` does the real work. | Delegate `clearCache()` to `flushPermissionCache()`. | Authz cache test proving both APIs flush. |
| B9 | `authz` | `SuperAdminCommand::getEmailColumn()` always returns `email`. | True | `packages/authz/src/Console/Commands/SuperAdminCommand.php:203-210` returns `email` in every branch. | Read a configured/user-model identifier column or document only `email` support. | Command test with non-standard user identifier if supported. |
| B10 | `authz` | `ImpersonateManager` updates the default guard session instead of the target guard. | True | Current file is `packages/authz/src/Services/ImpersonateManager.php`; `updatePasswordHashInSession()` receives `$guardName` but uses `$this->app['auth']->guard()` at line 366. | Use `$this->app['auth']->guard($guardName)`. | Multi-guard impersonation test. |
| B11 | `checkout` | `transitionStatus()` bypasses Spatie ModelStates and never calls `transitionTo()`. | False | `packages/checkout/src/Models/CheckoutSession.php:208-210` calls `$this->status->transitionTo($stateClass)` before the direct DB persistence at `232-238`. | No fix from this claim. Investigate only if event tests prove a separate issue. | Keep or add status-transition event tests before changing this path. |
| B12 | `docs` | `DocPayment` uses raw string status with no enum/validation. | True | `packages/docs/src/Models/DocPayment.php:106`; no `DocPaymentStatus` found. | Add `DocPaymentStatus` enum or central validation/transition path. | Tests for allowed/invalid payment statuses and transitions. |
| B13a | `docs` | `DocShareLink` stores a public plaintext `plainToken` property. | True | `packages/docs/src/Models/DocShareLink.php:45`; assigned in `packages/docs/src/Services/DocRenderService.php:114`. | Make token storage non-public and expose only for one-time return. | Test token availability for caller but not persisted/logged unintentionally. |
| B13b | `docs` | Public `plainToken` leaks through normal Eloquent JSON serialization. | Unverified runtime claim | Public property exists, but Eloquent serialization usually includes attributes/relations, not arbitrary public properties. No runtime proof was found. | Do not claim normal JSON leakage without a failing test; still remove public plaintext property because logs/dumps can expose it. | Serialization test for `toArray()`/`toJson()` and explicit log-object handling if relevant. |
| B14 | `affiliates` | `AffiliateCommissionRule` has fragile fallback logic around `commission_type`. | True | Current file is `packages/affiliates/src/Models/AffiliateCommissionRule.php`; fallback chain at `140-144`; `commission_type` is cast to `CommissionType`. | Simplify to casted enum access after confirming persisted string compatibility. | Unit tests for percentage/fixed commission calculations. |
| S1 | `chip`, `cashier-chip`, `cashier` | Eight payment models use `$guarded = []`. | True | `ChipModel.php:42`, `ChipIntegerModel.php:39`, `Webhook.php:54`, `cashier-chip` `Subscription.php:122`, `SubscriptionItem.php:62`, `StoredPaymentMethod.php:39`, `cashier` `UnifiedSubscriptionRecord.php:20`, `UnifiedInvoiceRecord.php:20`. | Replace with explicit `$fillable`; protect owner/status/amount fields. | Mass-assignment tests per package; run affected package suites. |
| S2 | `filament-affiliates` | Six resources do not explicitly call `forOwner()`. | Partially true | Listed resources return parent queries without explicit `forOwner()`. Several underlying models use `HasOwner` or package owner scopes; `affiliates.owner.enabled` defaults false. | Add explicit owner-safe resource queries or document and test reliance on global scopes. | Cross-tenant Filament resource tests with owner mode enabled. |
| S3 | `filament-docs` | Resources/widgets/pages use bare queries or lack owner-safe `getEloquentQuery()`. | Partially true | `DocTemplateResource`, `DocSequenceResource`, `DocEmailTemplateResource` have no override; widgets/pages use `Doc::query()`/`DocApproval::query()`. Docs models use `HasOwner`; `docs.owner.enabled` defaults false. | Add explicit owner-safe queries for resources, widgets, pages, route-bound surfaces. | Cross-tenant Filament/page/widget tests with owner mode enabled. |
| S4 | `filament-signals` | `SavedSignalReportResource` returns parent query without owner scoping. | True | `packages/filament-signals/src/Resources/SavedSignalReportResource.php:28-30`; sibling resources use `forOwner()`. | Use `SavedSignalReport::query()->forOwner()->with(...)` or `OwnerQuery`. | Cross-tenant resource test. |
| S5 | `promotions` | Promotion listeners query promotions without owner scoping. | True | `MarkPromotionAsUsedOnOrderPlaced.php:22`; `ReevaluatePromotionsOnCartUpdated.php:21-23`; owner feature config exists and defaults false. | Resolve cart/order owner and query via `forOwner()` or explicit global context. | Cross-tenant listener tests. |
| S6 | `cashier` | No rate limiting around Stripe gateway `charge()`, `refund()`, `createSubscription()`. | True | No `RateLimiter` usage in `packages/cashier/src`; Stripe gateway methods delegate directly. | Add configurable rate limiter/lock around payment operations; consider idempotency keys. | Tests for rate-limited charge/refund/subscription operations. |
| S7 | `jnt` | JNT config ships hardcoded test credentials. | False | `packages/jnt/config/jnt.php` credential keys are env-backed/null defaults; only public sandbox URLs/default environment are present. | No credentials fix. | None. |
| S8 | `jnt` | `JntWebhookLog` lacks `$fillable`/`$guarded` and extends Spatie `WebhookCall`. | False | `packages/jnt/src/Models/JntWebhookLog.php` extends `Model` and has `$fillable` at `94-108`; it does not extend Spatie `WebhookCall`. | No fix. | None. |
| S9 | `docs` | `DocShareLink::$plainToken` repeats B13 security concern. | Partially true | Same evidence as B13: public plaintext property exists; normal Eloquent JSON leak is unproven. | Same as B13. | Same as B13. |
| S10 | `affiliate-network` | DNS errors are suppressed with `@dns_get_record`. | True | `SiteContentFetcher.php:95,104`; `DnsVerificationStrategy.php:28`. | Remove suppression and handle DNS warning/false outcomes explicitly. | Unit test with resolver abstraction or controlled invalid domain behavior. |
| OS1 | `filament-growth` | Eleven query sites rely on automatic global `OwnerScope`. | Partially true | `rg` found many `Experiment::query()`, `Variant::query()`, `Assignment::query()` sites; resources often wrap with `OwnerUiScope`, but widgets/pages/actions still use bare queries in places. | Audit high-risk reads and replace with `OwnerUiScope`, `forOwner`, or explicit global context where needed. | Cross-tenant tests for widgets/pages and experiment result flows. |
| OS2 | `filament-authz` | `PermissionResource` uses `withoutGlobalScopes()` and would bypass `OwnerScope`. | Partially true | `packages/filament-authz/src/Resources/PermissionResource.php:39-44` calls `withoutGlobalScopes()`; permissions may be global/team-scoped rather than `HasOwner`. | Clarify permission tenancy model; if owner-scoped, reapply owner/team scoping explicitly after removing scopes. | Permission listing test under owner/team mode. |
| OS3 | `filament-pricing` | `TiersRelationManager` uses `method_exists($model, 'scopeForOwner')`. | True | `packages/filament-pricing/src/Resources/PriceListResource/RelationManagers/TiersRelationManager.php:115-116`. | Replace dynamic method check with `OwnerQuery::applyToEloquentBuilder()` or package owner helper. | Relation manager cross-tenant create/select tests. |
| OS4 | `events` | Many models rely on provider-registered owner scopes instead of direct `HasOwner`. | Partially true | `find packages/events/src/Models` shows 70 model files; direct `HasOwner` appears on a subset; `EventsServiceProvider.php:231-240` registers custom/polymorphic scopes. Exact 47/54 count is stale against 70 current model files. | Do not mass-add `HasOwner`; document/verify custom scope coverage for owner-owned models. | Owner-scoping contract tests for event models covered by provider scopes. |
| OS5 | `commerce-support` | `OwnerContext` static `$fallback` can leak in Octane. | Partially true | `packages/commerce-support/src/Support/OwnerContext.php:33,115-141,174-202`; fallback is static but `withOwner()` restores in `finally`; HTTP context stores state on request attributes. | Add concurrency/exception tests; avoid changing storage unless a leak reproduces. | Tests for nested `withOwner()`, exception restoration, and HTTP request isolation. |
| Z1 | `cashier` | Zero tests exist. | False | `find tests/src/Cashier -name '*Test.php'` found 21 files. | No broad test-suite creation; add tests only for verified bugs. | Run targeted cashier tests after fixes. |
| Z2 | `cashier-chip` | Pest exists but zero tests. | False | `find tests/src/CashierChip` found 49 test files. | No broad test-suite creation. | Run targeted tests after fixes. |
| Z3 | `docs` | Zero tests exist. | False | `find tests/src/Docs` found 21 test files. | No broad test-suite creation. | Add targeted DocPayment/DocShareLink tests. |
| Z4 | `filament-products` | Zero tests exist. | False | `find tests/src/FilamentProducts` found 7 test files. | No broad test-suite creation. | Add targeted tests only for touched surfaces. |
| Z5 | `filament-inventory` | Zero tests exist. | False | `find tests/src/FilamentInventory` found 7 test files. | No broad test-suite creation. | Add targeted tests only for touched surfaces. |
| Z6 | `filament-signals` | Zero tests exist. | False | `find tests/src/FilamentSignals` found 12 test files. | No broad test-suite creation. | Add SavedSignalReportResource owner test. |
| Z7 | `filament-growth` | Zero tests exist. | False | `find tests/src/FilamentGrowth` found 4 test files. | No broad test-suite creation. | Add targeted owner tests if modifying queries. |
| Z8 | `filament-vouchers` | Zero tests exist. | False | `find tests/src/FilamentVouchers` found 16 test files. | No broad test-suite creation. | Add targeted tests only for touched surfaces. |
| Z9 | `filament-promotions` | Zero tests exist. | False | `find tests/src/FilamentPromotions` found 5 test files. | No broad test-suite creation. | Add targeted tests only for touched surfaces. |
| Z10 | `filament-pricing` | Zero tests exist. | False | `find tests/src/FilamentPricing` found 17 test files. | No broad test-suite creation. | Add relation manager owner tests. |
| Z11 | `filament-cashier-chip` | Zero tests exist. | False | `find tests/src/FilamentCashierChip` found 14 test files. | No broad test-suite creation. | Add widget owner tests only if verified. |
| Z12 | `filament-contact` | Thin package has one test. | Partially true | Current package is `filament-contacting`, not `filament-contact`; `find tests/src/FilamentContacting` found 1 file. | Correct package name in audit; add tests only for real gaps. | Scoped tests if modifying package. |
| Z13 | `filament-communications` | Thin tests are blocked by autoloading bug. | Partially true | `find tests/src/FilamentCommunications` found 2 files; I1 confirms a PSR-4 issue for suites using `FixedOwnerResolver`. | Fix I1 first; then re-evaluate actual failures. | Run `./vendor/bin/pest --parallel tests/src/FilamentCommunications` after I1. |
| I1 | `tests` | `FixedOwnerResolver` path does not match its namespace and blocks dependent suites. | Partially true | `tests/src/CommerceSupport/OwnerResolvers/FixedOwnerResolver.php` namespace is `AIArmada\Commerce\Tests\Support\OwnerResolvers`; root `composer.json` maps `AIArmada\Commerce\Tests\` to `tests/src`. Many tests import this namespace. Impact "all suites" is overstated. | Move to `tests/src/Support/OwnerResolvers/FixedOwnerResolver.php` or add compatible class path without breaking imports. | `composer dump-autoload`; run representative suites that import it. |
| I2 | `checkout`, `chip`, `docs` | No package-local runner configs. | Partially true | No package-local `phpunit.xml`/`pest.xml`/`phpstan.neon` found; root `phpunit.xml` and `tests/src` exist and are current monorepo pattern. | Decide whether package-local configs are desired. Otherwise document root-scoped commands. | None until policy decision; use root runner with package paths. |
| I3a | `feedback` | Composer autoload maps factories directory that does not exist. | True | `packages/feedback/composer.json:33-37`; `packages/feedback/database` has migrations/seeders only. | Remove mapping or add factories if intended. | `composer dump-autoload`; package tests if affected. |
| I3b | `shipping` | Composer autoload maps factories directory that does not exist. | True | `packages/shipping/composer.json:18-22`; no `database/factories`. | Remove mapping or add factories. | `composer dump-autoload`; package tests if affected. |
| I3c | `shipping` | `registerEventListeners()` and `registerCommands()` are wired but empty. | True | `packages/shipping/src/ShippingServiceProvider.php:122-144`. | Remove empty hooks or add actual registrations. | Service provider smoke test if changed. |
| I4 | `cashier` | Prior audit claimed tests but zero exist. | False | Current tree has 21 files under `tests/src/Cashier`. | No broad test-suite work from this claim. | Add targeted tests for verified cashier bugs. |
| NAV1 | `filament-orders` | Static `$navigationGroup` remains on two pages. | True | `OrderFulfillmentPage.php:20`; `OrderTimelinePage.php:19`. | Replace with `getNavigationGroup()` reading `filament-orders.navigation.group`. | Static grep: `rg "static.*\\$navigationGroup" packages/filament-*/src`. |
| NAV2 | `filament-engagement` | Seven resources use static `$navigationSort`. | True | `FollowResource`, `BookmarkResource`, `BookmarkCollectionResource`, `ResponseResource`, `ReactionResource`, `SubscriptionResource`, `ReminderResource` around lines 27-29. | Replace with config-backed `getNavigationSort()`. | Static grep and package tests. |
| NAV3 | `filament-cart` | Four resources/pages use static `$navigationSort`. | True | `CartDashboard.php:23`, `CartItemResource.php:35`, `ConditionResource.php:36`, `LiveDashboardPage.php:21`. | Replace with config-backed `getNavigationSort()`. | Static grep and package tests. |
| NAV4 | `filament-shipping` | Shipping pages hardcode group strings and static sort. | True | `ShippingDashboard.php:25,32-34`; `ManifestPage.php:46,53-55`; config has nested `navigation.group`. | Use config-backed `getNavigationGroup()`/`getNavigationSort()` and add missing config sort if needed. | Static grep and package tests. |
| NAV5 | `filament-cashier` | Resources keep static `$navigationSort` alongside getters. | True | `UnifiedSubscriptionResource.php:42-47`; `UnifiedInvoiceResource.php:37-42`. | Remove dead static property; keep config-backed getter. | Static grep. |
| EXC1 | `docs` | Exception hierarchy inadequate for financial document engine. | Partially true | `find packages/docs/src -path '*/Exceptions/*'` found zero files in current tree, not two. | Add base exceptions only when needed by DocPayment/share/payment fixes. | Tests for thrown typed exceptions. |
| EXC2 | `feedback` | No custom exceptions. | True | `find packages/feedback/src -path '*/Exceptions/*'` count `0`. | Add hierarchy only with real exception use cases. | Tests when introduced. |
| EXC3 | `engagement` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC4 | `signals` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC5 | `growth` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC6 | `orders` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC7 | `promotions` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC8 | `moderation` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC9 | `communications` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC10 | `cashier-chip` | Seven exceptions extend `Exception` without base `CashierChipException`. | True | `find packages/cashier-chip/src/Exceptions` count `7`. | Add base only if stabilizing public exception contracts. | Exception type tests. |
| EXC11 | `products` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC12 | `contacting` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC13 | `customers` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC14 | `inventory` | Two exceptions extend `Exception` without base. | True | `find packages/inventory/src/Exceptions` count `2`; classes extend `Exception`. | Add base only if keeping both exceptions. | Exception type tests. |
| EXC15 | `membership` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC16 | `pricing` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| EXC17 | `tax` | Missing `TaxCalculationException`; one exception exists. | Partially true | `TaxZoneNotFoundException` exists; no `TaxCalculationException`; tax service failures not fully audited. | Add calculation exception only if used by calculator error paths. | Tax calculation failure tests. |
| EXC18 | `references` | No custom exceptions. | True | Count `0`. | Same as EXC2. | Same. |
| DOCN1 | `filament-growth` | Docs show flat `navigation_group`. | True | `packages/filament-growth/docs/03-configuration.md:13,40,96`. | Update to nested `navigation.group`. | Docs grep for `navigation_group`. |
| DOCN2 | `filament-jnt` | Docs/README show flat `navigation_group`. | True | `README.md:48`; `docs/02-installation.md:244`; `docs/03-configuration.md:32,82,87`. | Update docs. | Docs grep. |
| DOCN3 | `filament-promotions` | Docs/README show flat `navigation_group`. | True | `README.md:52`; `docs/03-configuration.md:21,65`; also `docs/99-troubleshooting.md:147`. | Update docs. | Docs grep. |
| DOCN4 | `filament-vouchers` | README and docs show flat `navigation_group`. | True | `README.md:50`; `docs/03-configuration.md:22,98`. | Update docs. | Docs grep. |
| DOCN5 | `filament-cart` | Docs show flat `navigation_group`. | True | `README.md:99`; `docs/03-configuration.md:26`. | Update docs. | Docs grep. |
| DOCN6 | `filament-chip` | README shows flat `navigation_group`. | True | `packages/filament-chip/README.md:46`. | Update docs. | Docs grep. |
| DOCN7 | `filament-pricing` | Docs show deprecated static navigation examples. | True | `docs/05-resources.md:21,115`; `docs/06-pages-widgets.md:21,109`. | Replace examples with config-backed getters. | Docs grep for `$navigationGroup`. |
| DOCN8 | `filament-affiliates` | Docs show flat `navigation_group`. | True | `docs/03-configuration.md:21,133`. | Update docs. | Docs grep. |
| DOCN9 | `filament-tax` | Docs show deprecated static/plugin navigation examples. | Partially true | `docs/06-settings.md:109`; `docs/07-customization.md:24`; plugin docs also mention `navigationGroup()` at `docs/03-configuration.md:91,158-169`. | Update examples to repo config standard or explicitly document plugin API if still supported. | Docs grep. |
| DOCP1 | `filament-orders` | Docs mention phantom config keys. | True | Config has only `navigation`, `pages`, `payment_gateways`; docs mention `enable_invoice_download`, `tables.poll_interval`, `tables.date_format` at `docs/03-configuration.md:52,72-73,85`, `docs/05-customization.md:205,213`, `docs/99-troubleshooting.md:99`. | Remove docs or add real config/code if behavior is intended. | Config/docs comparison. |
| DOCP2 | `filament-shipping` | Docs mention five absent config keys. | Partially true | Config lacks `navigation.sort`, `table_poll_interval`, and docs feature keys `fulfillment_queue`, `manifest_page`, `dashboard`; current config has `features.enable_fulfillment_queue`. | Sync docs and config/code names. | Config/docs comparison. |
| DOCP3 | `filament-events` | Docs miss resources and document non-existent env var. | Partially true | `EventRegistrationParticipantResource` exists but is not registered; `EventChangeLogResource` is registered but not in overview. `docs/02-installation.md:37` documents `FILAMENT_EVENTS_NAVIGATION_GROUP` not read by config. | Update resource docs; decide whether participant resource should be registered or removed. | Plugin registration/doc test or static check. |
| DOC1 | `contacting` | Stale `specification.md` exists at package root. | False | `find packages/contacting` shows no `specification.md`. Audit package report mentions it, but current tree does not. | No fix. | None. |
| DOC2 | `addressing` | README has version mismatch in install example. | False | `packages/addressing/README.md:1-35` is a documentation pack index with no install example/version. | No fix. | None. |
| DEP1 | `tax` | Used dependencies are missing from composer. | Partially true | `TaxResultData` uses `spatie/laravel-data` and `akaunting/money`; settings use `spatie/laravel-settings`; models use `owen-it/laravel-auditing`; TaxExemption uses `spatie/laravel-model-states`; `packages/tax/composer.json` only requires PHP and commerce-support. | Add required dependencies or remove usages. | `composer validate`; package tests/PHPStan. |
| DEP2 | `cashier` | `laravel/cashier` is hard-used but only suggested. | True | `packages/cashier/composer.json:20-27`; multiple `use Laravel\Cashier\...` in `packages/cashier/src/Gateways/Stripe/*` and `StripeGateway.php`. | Either require `laravel/cashier` or isolate Stripe classes behind optional integration that is not autoloaded without dependency. | Composer install test without Laravel Cashier; PHPStan with/without optional deps if supported. |
| CFG1 | `filament-customers` | Three feature keys are read but absent from config. | True | Config only has `navigation.group`; plugin reads `features.merge_customers`, `features.segment_rebuild`, `features.address_validation` at `FilamentCustomersPlugin.php:67,71,75`. | Add keys or remove feature gates. | Config read test/static config comparison. |
| CFG2 | `filament-cashier` | `billing_portal.login_enabled` read but absent from config. | True | Config defines `billing_portal` but no `login_enabled`; `BillingPanelProvider.php:59` reads `$config['login_enabled'] ?? true`. | Add config key and docs or remove read. | Config docs test/static comparison. |
| CFG3 | `filament-chip` | `tables.amount_precision` is defined but never read. | False | It is read in `PurchaseTable.php:175` and `PurchaseInfolist.php:236`; config at `config/filament-chip.php:26`. | No fix. | None. |
| CQ1 | `cashier` | Duplicate `Billable` trait exists at root and `Concerns`. | Partially true | Files are functionally duplicated except namespace/import: `packages/cashier/src/Billable.php` and `packages/cashier/src/Concerns/Billable.php`. | Decide one public namespace; deprecate/remove duplicate only with migration notes. | PHPStan and tests for consuming trait. |
| CQ2 | `cashier` | `CurrencyFormatter` is an unnecessary thin wrapper. | True | `packages/cashier/src/Support/CurrencyFormatter.php:7-39` delegates to `CommerceSupport\Support\MoneyFormatter`. | Replace usages or keep as BC facade if public API. | Search for usages; tests if public helper removed. |
| CQ3 | `affiliates` | `AffiliateConversion::booted()` contains large inline balance logic. | True | Created/updated lifecycle balance logic spans `AffiliateConversion.php:171-260+`. | Extract to Action/service if behavior changes are needed. | Conversion lifecycle tests before refactor. |
| CQ4 | `products` | Ten models use `$guarded = ['id']`. | True | `rg guarded packages/products/src/Models` found 10 model files. | Convert to explicit `$fillable` only when touching models or as scoped consistency pass. | Mass-assignment tests for protected owner/status fields. |
| CQ5 | `customers` | Five models use `$guarded = ['id']`. | True | `rg guarded packages/customers/src/Models` found 5 model files. | Same as CQ4. | Same. |
| CQ6 | `growth` | Owner columns are fillable on three models. | True | `Assignment.php:71-72`, `Experiment.php:91-92`, `Variant.php:70-71`. | Remove direct owner fillability or protect writes with owner primitives. | Cross-tenant mass-assignment tests. |
| CQ7 | `cashier-chip` | Composer alias points to missing facade file. | True | `packages/cashier-chip/composer.json:32-34`; no `packages/cashier-chip/src/Facades` directory. | Add facade or remove alias. | Package discovery/facade resolution test. |
| CQ8 | `chip` | Purchase model uses mutable datetime casts. | True | `packages/chip/src/Models/Purchase.php:195-196`; other chip models also use `datetime`. | Change lifecycle timestamp casts to `immutable_datetime` where safe. | Model cast tests; watch BC for mutable Carbon expectations. |
| CQ9 | `filament-events` | `EventRegistrationParticipantResource` is implemented but never registered. | True | Resource exists; `FilamentEventsPlugin.php:49-79` registers 8 resources but not participant. | Register it with config key, nest under registration, or remove if intentionally unused. | Plugin resource registration test. |
| CQ10 | `filament-addressing` | `AddressCountryFormSchema` is orphaned. | True | `AddressCountryFormSchema.php:11`; `rg` found no usage outside itself. | Use it in a resource or remove it. | Static usage check. |
| CQ11 | `filament-commerce-support` | `buildSidebarForForm()` has a no-op loop. | True | `ManageCommerceNavigation.php:118-124` loops without side effects. | Remove the block or implement intended behavior. | Unit/feature test for sidebar grouping. |
| CQ12 | `products` | Policy helper methods are duplicated. | True | Six policies contain private `belongsToOwner()` and `isGlobalModel()` helpers. | Extract shared helper/trait if editing policies. | Policy tests. |
| CQ13 | `filament-cart` | Stale `.bak` file exists in tests. | False | `find packages/filament-cart -name '*.bak'` returned no files. | No fix. | None. |
| CQ14 | `cashier` | Commented-out route stubs remain. | True | `packages/cashier/routes/web.php:18-22`. | Remove stubs or replace with docs comment. | Route list smoke check if changed. |
| CQ15 | `checkout` | `CheckoutStatus` enum is unused dead code. | Partially true | Source usage only `packages/checkout/src/Enums/CheckoutStatus.php`; docs reference it at `docs/06-payment-gateways.md:263`. | Remove or update docs/code to avoid stale examples. | Static grep and checkout docs check. |
| CQ16 | `vouchers` | `VoucherAssignment` is not auditable. | True | Auditable models include `Voucher`, `VoucherTransaction`, `VoucherWallet`, `VoucherUsage`; `VoucherAssignment.php:28` does not implement `Auditable`. | Add auditing if assignment revocations are compliance-relevant. | Auditing test similar to existing voucher tests. |
| CQ17 | `vouchers` | `VOUCHER_METADATA_KEY` is duplicated. | True | `ValidateVoucherOnCheckout.php:20`; `VoucherConditionProvider.php:25`. | Move to shared constant. | Existing voucher cart integration tests. |
| CQ18 | `inventory` | Seven models are not final. | True | Non-final model classes: `InventoryDemandHistory`, `InventoryValuationSnapshot`, `InventoryBackorder`, `InventoryReorderSuggestion`, `InventoryCostLayer`, `InventorySupplierLeadtime`, `InventoryStandardCost`. | Mark final only if extension is not supported. | PHPStan/tests after finalization. |
| CQ19 | `inventory` | Deprecated `InsufficientStockException` is still shipped. | True | `packages/inventory/src/Exceptions/InsufficientStockException.php:10,13,27`. | Remove in breaking-change pass or keep as BC alias with docs. | Exception compatibility tests. |
| CQ20 | `engagement` | Statuses use class constants instead of PHP enums. | True | Multiple models define `STATUS_*`, e.g. `Follow`, `Subscription`, `Reminder`, `Share`, `BookmarkCollection`. | Optional enum standardization; not urgent defect. | Migration/cast tests if changed. |
| CQ21 | `signals` | No enum usage for statuses/results. | Partially true | Signals stores status-like strings in services/actions, but not all are domain lifecycle statuses. | Do not blanket-enum response payloads; only enum persisted state fields if useful. | Tests around any converted fields. |
| CQ22 | `cashier-chip` | Status values use class constants instead of PHP enums. | True | `Subscription.php:101-115`; `Payment.php:30-55`; action code uses raw strings in places. | Optional enum standardization after payment behavior tests exist. | Subscription/payment status tests. |
| CQ23 | `references` | `getPart()` accepts `string` while sibling methods use enum. | True | `HasReferenceParts.php:11` accepts `string`; `setPart`/`removePart` use enum values. | Change signature or add enum overload carefully. | Reference part helper tests. |
| CQ24 | `checkout` | `checkout_sessions` has 11 JSON columns and standard B-tree indexes cannot query JSON paths. | Partially true | Migration has configurable JSON columns at `2024_01_01_000001_create_checkout_sessions_table.php:35-47`; count is 8 JSON columns, not 11; config has `database.json_column_type`. Index concern is a design/performance risk, not proven bug. | Do not add config; add generated columns/indexes only for proven hot queries. | Query plan/benchmark before index work. |
| CQ25 | `checkout` | Checkout has eight hard sibling dependencies. | True | `packages/checkout/composer.json:12-22` requires cart, support, customers, docs, orders, pricing, products, shipping, webhook-client. | Architecture review only; do not change without package-boundary design. | Standalone install/integration tests if dependencies change. |
| CQ26 | `membership` | Contracts lack no-op defaults and actions use `app()->bound()`. | True | `ApproveMembershipApplicationAction`, `RejectMembershipApplicationAction`, `ApplyForMembershipAction`, `RemoveMemberAction`, `ChangeMemberRoleAction` call `app()->bound()` for contracts. | Add default null/no-op implementations if extension contract should always resolve. | Action tests with and without bindings. |
| CQ27 | `moderation` | No contracts/interfaces; actions are concrete singletons. | True | `ModerationServiceProvider.php:25-26` registers concrete `BlockEntityAction` and `RecordModerationAction`; no contracts found. | Add contracts only if alternate moderation implementations are expected. | Binding tests if introduced. |
| CQ28 | `signals` | No domain events; 21 listeners consume external events. | Partially true | `packages/signals/src/Listeners` contains 21 listeners; package emits jobs/alerts but domain-event model is intentionally integration-heavy per docs. | Requires architecture/product decision; not a defect by itself. | N/A until approved. |
| CQ29 | `jnt` | Legacy flat command stubs are shipped. | True | Deprecated command classes include `HealthCheckCommand`, `WebhookTestCommand`, `ConfigCheckCommand`, `OrderTrackCommand`, `OrderCreateCommand`, `OrderPrintCommand`, `OrderCancelCommand`. Claim says 4, current tree has more. | Remove in breaking-change pass or keep aliases with deprecation docs. | Artisan command list tests. |
| CQ30 | `jnt` | No application-level cascades on related records. | False | `JntOrder::booted()` deletes items/parcels/tracking events and nulls webhook logs at `JntOrder.php:252-257`; child models validate owner on create. | No cascade fix. | Existing/future JNT delete cascade tests. |
| A1 | `addressing` | `area_sources` config is only consumed by command, no provider auto-registration. | True | `config/addressing.php:23-24`; `ImportAddressAreasCommand.php:23-51`; docs say sources are available to command. | Product decision: add registrar only if auto-discovery is desired. | Command tests; provider tests if adding auto-registration. |
| A2 | `checkout` | Eight hard dependencies risk cascading breakage. | True | Same evidence as CQ25. | Architecture review only. | Standalone/integration tests if changed. |
| A3 | `commerce-support` | `NoCurrentOwnerException` extends `RuntimeException` instead of `CommerceException`. | True | `NoCurrentOwnerException.php:10`; `CommerceException.php:16`. | Change only if public exception hierarchy BC is acceptable. | Exception type tests. |
| A4 | `commerce-support` | `OwnerContext` static fallback may leak in Octane. | Partially true | Same evidence as OS5. | Test before changing. | Same as OS5. |
| A5 | `events` | Many event models rely on provider scope registration. | Partially true | Same evidence as OS4. | Verify custom scope coverage. | Same as OS4. |
| A6 | `engagement` | `BookmarkCollection` lacks cascades for collection items. | Unverified runtime claim | Not fully inspected in this pass; claim needs model relationship/delete path proof. | Verify before planning. | Delete collection regression test if confirmed. |
| A7 | `filament-shipping` | Owner scoping code is repetitive. | Partially true | Multiple resources/pages repeat `getNavigationGroup()` and owner scoping patterns; no defect proven. | Refactor only when touching those files for verified defects. | Existing Filament shipping tests. |
| A8 | `filament-pricing` | `TiersRelationManager` uses `method_exists` instead of owner helper. | True | Same evidence as OS3. | Same as OS3. | Same as OS3. |
| CS1 | multiple | Three different `$guarded`/`$fillable` approaches exist. | True | Payment `$guarded = []`; products/customers `$guarded = ['id']`; most packages use explicit `$fillable`. | Standardize incrementally, starting with payment/security rows. | Mass-assignment tests per package. |
| CS2 | filament packages | Four owner-scoping patterns in Filament. | Partially true | Verified examples: direct `forOwner`, `OwnerUiScope`, parent query relying on global scopes, `withoutGlobalScopes`. | Define a Filament owner-scoping standard before broad refactor. | Cross-tenant resource/widget tests. |
| CS3 | `cashier`, `cashier-chip` | Action pattern mismatch: `cashier` uses Actions, `cashier-chip` plain classes. | True | Cashier has `src/Actions/*`; cashier-chip has plain action classes without `AsAction`. | Optional consistency; do not change without API decision. | Action tests if converted. |
| CS4 | `engagement`, `signals`, `cashier-chip` | PHP enums and class constants are inconsistent. | Partially true | True for engagement/cashier-chip; signals has status-like strings but many are response payloads. | Do not blanket-convert; evaluate persisted lifecycle statuses. | Status tests. |
| CS5 | `chip`, `customers` | `down()` methods violate monorepo convention. | Partially true | `chip` has 9 `down()` across 10 migrations; `customers` has 7/7. Monorepo says no `down()` required, not strictly forbidden in the pasted database rules. | Cleanup only in migration-style pass; avoid churn. | Migration smoke tests if changed. |
| CS6 | all packages | No `CHANGELOG.md` in 57 packages. | Requires product approval | No package changelogs found, but user explicitly excluded creating 57 changelogs in this pass. | Do not include in remediation unless separately approved. | N/A. |
| CS7 | `checkout`, `chip`, `docs` | No package-local test config. | Partially true | Same as I2. | Same as I2. | Same as I2. |
| R1 | payment packages | Replace `$guarded = []` with `$fillable`. | Derived from true finding | Backed by S1/B4. | P1 security fix. | Mass-assignment tests. |
| R2 | `filament-affiliates` | Add owner scoping to six resources. | Derived from true finding | Backed by S2 true portion. | P1 owner fix. | Cross-tenant Filament tests. |
| R3 | `promotions` | Fix `times_used` to `usage_count`. | Derived from true finding | Backed by B1. | P1 bug fix. | Listener test. |
| R4 | `affiliate-network` | Fix archive command statuses. | Derived from true finding | Backed by B2. | P1 bug fix. | Command test. |
| R5 | `chip` | Fix `WebhookReceived::isTest()` default. | Derived from true finding | Backed by B3. | P1 webhook fix. | Event tests. |
| R6 | `filament-docs` | Add explicit owner scoping to resources/widgets/pages. | Derived from true finding | Backed by S3 true portion. | P1 owner fix. | Cross-tenant tests. |
| R7 | `tests` | Fix `FixedOwnerResolver` path. | Derived from true finding | Backed by I1 true portion. | P0 CI unblocker. | Composer autoload + representative suites. |
| R8 | `affiliates` | Fix `FraudDetectionService` rule dispatch. | False | B7 crash claim is false for current rules. | Do not implement unless split interfaces are introduced. | N/A. |
| R9 | `cashier` | Add cashier suite with at least 50 tests. | False | Z1/I4 are false; 21 cashier tests exist. | Add targeted tests only for fixes. | Targeted package tests. |
| R10 | `docs` | Add docs suite with at least 30 tests. | False | Z3 is false; 21 docs tests exist. | Add targeted tests only for fixes. | Targeted package tests. |
| R11 | `cashier` | Fix error swallowing. | Derived from true finding | Backed by B5 true portion. | P1/P2 gateway reliability fix. | Gateway exception tests. |
| R12 | `authz` | Fix `clearCache()` no-op. | Derived from true finding | Backed by B8. | P1 API bug fix. | Authz cache tests. |
| R13 | `authz` | Fix `getEmailColumn()`. | Derived from true finding | Backed by B9. | P2 command behavior fix. | Command tests. |
| R14 | `authz` | Fix `ImpersonateManager` wrong guard. | Derived from true finding | Backed by B10. | P1 auth/session fix. | Multi-guard test. |
| R15 | `checkout` | Fix `transitionStatus()` to use HasStates. | False | B11 is false; current code already calls `transitionTo()`. | No fix. | Keep transition tests. |
| R16 | `docs` | Add `DocPaymentStatus` enum. | Derived from true finding | Backed by B12. | P2 data integrity fix. | Status tests. |
| R17 | `docs` | Fix `DocShareLink` public token. | Derived from true finding | Backed by B13/S9 true portion. | P1 token exposure hardening. | Serialization/logging tests. |
| R18 | `promotions` | Add owner scoping to listeners. | Derived from true finding | Backed by S5. | P1 owner fix. | Cross-tenant listener tests. |
| R19 | `filament-cashier-chip` | Add owner scoping to dashboard widgets. | Unverified runtime claim | Roadmap item not supported by a specific verified row in `FINDINGS.md`; not inspected fully. | Verify before implementing. | Widget cross-tenant tests if confirmed. |
| R20 | `cashier` | Cache `Schema::hasColumn()`. | Derived from true finding | Backed by B6a. | P2 performance fix. | Cache tests/benchmark. |
| R21 | `cashier-chip` | Create missing facade file. | Derived from true finding | Backed by CQ7. | P3 integration fix. | Facade resolution test. |
| R22 | filament packages | Add tests to filament-inventory/products. | False | Z4/Z5 are false. | Add targeted tests only when changing behavior. | Targeted tests. |
| R23 | 18 packages | Add base exception hierarchies. | Partially true | EXC rows are mostly true, but this is architecture debt, not urgent defect. | P4 only, package-by-package with actual thrown exceptions. | Exception contract tests. |
| R24 | docs | Fix flat `navigation_group` docs. | Derived from true finding | Backed by DOCN rows. | P3 docs fix. | Docs grep. |
| R25 | all packages | Add `CHANGELOG.md` to all packages. | Requires product approval | CS6; user excluded documentation-only creations beyond `FIX.md`. | Do not do without approval. | N/A. |
| R26 | filament packages | Fix navigation violations. | Derived from true finding | Backed by NAV rows. | P3 Filament rule fix. | Static grep/tests. |
| R27 | `cashier` | Remove duplicate `Billable` trait and `CurrencyFormatter`. | Partially true | CQ1/CQ2 true portions; public API impact unknown. | P4 cleanup with BC decision. | Trait/helper usage tests. |
| R28 | `affiliates` | Extract balance logic. | Derived from true finding | Backed by CQ3. | P4 refactor. | Conversion lifecycle tests. |
| R29 | `products` | Remove duplicate policy helpers. | Derived from true finding | Backed by CQ12. | P4 refactor. | Policy tests. |
| R30 | `chip` | Fix mutable datetime casts. | Derived from true finding | Backed by CQ8. | P3 consistency fix. | Cast tests. |
| R31 | multiple | Remove stale assets. | Partially true | Some assets are true (CQ9-CQ11, CQ14, CQ29); `.bak`, JNT cascades, contacting spec are false. | Only remove verified stale assets. | Static greps/tests per package. |
| R32 | `contacting` | Remove stale implementation spec. | False | DOC1 is false; file absent. | No fix. | None. |
| R33 | `tax` | Add missing composer deps. | Partially true | DEP1 true for four/five packages; verify exact package names/versions. | P3 dependency fix. | Composer/PHPStan/tests. |
| R34 | `products`, `customers` | Standardize `$fillable`. | Derived from true finding | Backed by CQ4/CQ5. | P4 consistency unless security-sensitive fields are exposed. | Mass-assignment tests. |
| R35 | `engagement`, `signals`, `cashier-chip` | Standardize PHP enums. | Partially true | CQ20-CQ22/CS4. | P4 architecture, not defect. | Status tests. |
| R36 | `chip`, `customers` | Remove `down()` methods. | Partially true | CS5 true for existence but not mandatory defect. | P4 style cleanup only if approved. | Migration smoke tests. |
| R37 | `references` | Fix `getPart()` type hint. | Derived from true finding | Backed by CQ23. | P4 consistency. | Reference helper tests. |
| R38 | `inventory` | Remove deprecated exception. | Derived from true finding | Backed by CQ19. | P4 breaking cleanup. | Exception tests. |
| R39 | `inventory` | Mark models `final`. | Derived from true finding | Backed by CQ18. | P4 consistency. | PHPStan/tests. |
| R40 | `jnt` | Remove legacy command stubs. | Derived from true finding | Backed by CQ29. | P4 breaking cleanup. | Artisan command tests. |
| R41 | `checkout` | Remove unused `CheckoutStatus` enum. | Partially true | CQ15 true for source usage but docs reference it. | Update docs/code together if removed. | Static grep/docs tests. |
| R42 | filament packages | Standardize owner scoping in Filament. | Partially true | CS2, S2-S4, OS rows. | P4 standardization after critical owner leaks fixed. | Cross-tenant tests. |
| R43 | filament packages | Add tests to remaining untested filament packages. | False | Zero-test claims were false for listed packages. | Add targeted missing tests only. | Targeted tests. |

## Corrections For False Or Partial Claims

- `checkout` status transitions should not be rewritten blindly. Current code already calls `transitionTo()` before its direct DB persistence.
- Broad zero-test claims are stale. The current monorepo suite lives under `tests/src`; most listed packages have tests there.
- The `FixedOwnerResolver` autoload issue is real, but "blocks all test suites" is too broad. It blocks suites importing that resolver.
- JNT does not ship hardcoded credentials in config, and `JntWebhookLog` does have `$fillable`.
- JNT does have application-level delete behavior on `JntOrder`; do not plan a cascade fix from the stale claim.
- The `contacting/specification.md` cleanup is stale because that file no longer exists.
- `filament-chip.tables.amount_precision` is read in current code.
- Checkout JSON columns already use `checkout.database.json_column_type`; do not add a duplicate config key.
- `FraudDetectionService` is not currently a method-not-found crash because the present `FraudRule` contract requires both methods.
- `DocShareLink::$plainToken` is public and should be hardened, but normal Eloquent JSON leakage was not proven.

## Remediation Plan

### P0: Unblock Verification And CI

1. Fix `FixedOwnerResolver` PSR-4 path without changing imports.
2. Run `composer dump-autoload`.
3. Run representative affected suites, for example `./vendor/bin/pest --parallel tests/src/CommerceSupport tests/src/Chip tests/src/FilamentPromotions`.
4. Record any remaining suite blockers before source fixes depend on test feedback.

### P1: Critical Security, Tenant Isolation, And Payment/Webhook Behavior

1. Replace payment-package `$guarded = []` with explicit `$fillable`; protect owner, status, amount, and gateway identifier fields.
2. Harden `chip` webhook/test classification defaults and `Webhook` mass assignment.
3. Add explicit owner-safe queries to `filament-affiliates`, `filament-docs`, `filament-signals`, and `promotions` listeners using `forOwner`, `OwnerQuery`, `OwnerWriteGuard`, or explicit global context.
4. Fix `promotions` usage count and `affiliate-network` archive statuses.
5. Fix `authz` impersonation guard and `clearCache()`.
6. Fix `docs` plaintext share token handling.
7. Separate cashier gateway not-found responses from auth/network/rate-limit failures.

### P2: Behavioral Bugs, State Integrity, And Performance

1. Add `DocPaymentStatus` or central payment-status validation.
2. Fix `authz` super-admin email column behavior or document the `email` requirement.
3. Cache `cashier` owner-scope schema column checks after adding tests.
4. Add configurable cashier payment-operation rate limiting/idempotency.
5. Simplify `AffiliateCommissionRule` enum usage after commission calculation tests.
6. Verify and fix `filament-growth`, `filament-authz`, and `filament-pricing` owner-scoping concerns where tests prove gaps.

### P3: Config, Composer, Docs, Navigation, And Integration Gaps

1. Add or remove the `cashier-chip` facade alias.
2. Resolve tax composer dependency mismatch, including whether every listed package is truly required.
3. Add missing `filament-customers` and `filament-cashier` config keys or remove their reads.
4. Fix Filament static navigation violations with config-backed getters.
5. Sync stale Filament navigation docs and phantom config docs.
6. Update `filament-events` docs/registration for `EventChangeLogResource` and `EventRegistrationParticipantResource`.
7. Update chip datetime casts where lifecycle timestamps should be immutable.

### P4: Architecture And Consistency Improvements

1. Standardize model fillability outside payment packages only with targeted mass-assignment tests.
2. Resolve duplicate `cashier` Billable/CurrencyFormatter APIs with a BC decision.
3. Extract affiliate balance lifecycle logic to an Action after tests lock current behavior.
4. Add exception bases only where packages actually throw package-specific exceptions.
5. Remove or integrate orphaned Filament/Event/JNT code paths only when no public API depends on them.
6. Consider enum standardization, `final` models, `down()` cleanup, package-local test configs, and changelogs as separate approved cleanup/product work.

## Later Fix Verification Commands

- `./vendor/bin/pest --parallel tests/src/<PackageOrFeature>`
- `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`
- `./vendor/bin/pint <changed-files>`
- `rg -n -- "constrained\\(|cascadeOnDelete\\(" packages/*/database`
- `rg "static.*\\$navigationGroup" packages/filament-*/src`
- `rg "'navigation_group'" packages/filament-*/config packages/filament-*/docs`

No source tests were run for this pass because the only mutation is this verification/planning document.
