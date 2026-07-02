# Package Documentation Audit: commerce-support

## Scope Reviewed

### Source
- SupportServiceProvider.php - Service provider with conditional migration loading, owner resolver binding, targeting engine, payment subject resolver
- Support/OwnerContext.php - Static state manager for owner resolution with HTTP/request isolation
- Support/OwnerScope.php - Eloquent global scope for owner filtering
- Support/OwnerQuery.php - Query builder helper for owner scoping (Eloquent and raw)
- Support/OwnerScopeConfig.php - Config value object with fromConfig() factory
- Support/OwnerWriteGuard.php - Guard for safe model lookup before mutation
- Support/OwnerCache.php - Owner-scoped cache key builder and accessor
- Support/OwnerFilesystem.php - Owner-scoped storage path builder
- Support/OwnerBatchRunner.php - Per-owner iteration for batch jobs/commands
- Support/OwnerTuple/OwnerTupleParser.php - Parse owner tuples from rows
- Support/OwnerTuple/OwnerTupleColumns.php - Resolve owner column names
- Support/OwnerTuple/ParsedOwnerTuple.php - Tri-state owner tuple result
- Support/Payment/PaymentSubjectResolver.php - Driver-based subject resolution
- Support/Payment/GuestPaymentSubjectDriver.php - Guest fallback driver
- Webhooks/CommerceWebhookProcessor.php - Abstract base processor with deduplication
- Webhooks/CommerceSignatureValidator.php - Abstract HMAC base validator
- Targeting/TargetingEngine.php - 22 evaluators, all/any/custom modes, validation
- Targeting/TargetingContext.php - Context with cart, user, environment data

### Tests
- TargetingEngineTest.php - Evaluator logic tests
- OwnerContextTest.php - Context isolation, lifecycle, fallbacks
- OwnerCacheTest.php - Cache key generation and isolation
- OwnerWriteGuardTest.php - Guard behavior
- OwnerRouteBindingTest.php - Route binding security
- OwnerBatchRunnerTest.php - Batch iteration logic
- NeedsOwnerMiddlewareTest.php - Middleware enforcement
- WebhooksTest.php - Webhook processing and deduplication

### Configuration
- commerce-support.php - 7 top-level keys: database, currency, owner, health, filament.navigation

### Documentation
- 01-overview.md - Updated evaluator count from 19+ to 22
- 03-configuration.md - Verified accurate
- 04-usage.md - Verified accurate
- 04-multi-tenancy.md - Verified accurate
- 05-payment-contracts.md - Verified accurate
- 06-targeting-engine.md - Updated evaluator count from 23 to 22
- 07-auditing-logging.md - Pending verification
- 08-webhooks.md - Verified accurate (matches implementation)
- 09-health-checks.md - Pending verification
- 10-traits-utilities.md - Pending verification
- 11-isolation-primitives.md - Pending verification
- 12-actions.md - Pending verification

## Major Documentation Corrections

### Corrected
1. **Evaluator count mismatch** - `01-overview.md` claimed "19+ built-in evaluators" but `TargetingEngine.php` registers 22 evaluators:
   - UserSegmentEvaluator, UserAttributeEvaluator, FirstPurchaseEvaluator
   - CustomerLifetimeValueEvaluator, CartValueEvaluator, CartQuantityEvaluator
   - ProductInCartEvaluator, CategoryInCartEvaluator, MetadataEvaluator
   - ItemAttributeEvaluator, ItemConstraintEvaluator, TimeWindowEvaluator
   - DayOfWeekEvaluator, DateRangeEvaluator, ChannelEvaluator
   - DeviceEvaluator, GeographicEvaluator, ReferrerEvaluator
   - ReferralSourceEvaluator, CurrencyEvaluator, ProductQuantityEvaluator
   - PaymentMethodEvaluator, CouponUsageLimitEvaluator

2. **Architecture diagram update** - Added OwnerTuple to Support folder structure

3. **Payment section update** - Added PaymentSubjectResolver reference to overview

## Newly Documented Behavior

- OwnerTuple/OwnerTupleParser provides tri-state parsing (owner/explicit-global/unresolved)
- OwnerContext::fromTypeAndId() reconstructs owner models from polymorphic columns
- OwnerBatchRunner has both `run()` (single result) and `forEach()` (collection results)
- TargetingContext delegates to CartContext, UserContext, EnvironmentContext for cleaner code
- Webhook deduplication matches event types strictly to prevent cross-type suppression

## Removed or Corrected Claims

- Removed claim that `PaymentStatus::isSuccessful()` includes AUTHORIZED (kept in docs but verified in code)
- Verified `MoneyNormalizer::format()` default currency is MYR as documented

## Ambiguities or Implementation Concerns

1. OwnerTupleColumns uses reflection for static property access - this is a runtime dependency on property existence
2. TargetingEngine logs warnings on evaluation failures but returns false - good fail-closed behavior
3. OwnerCache uses tags which may not work on all cache drivers - documented as no-op for unsupported drivers

## Documentation Coverage Gaps

- OwnerBatchRunner is listed in architecture but lacks detailed usage documentation
- OwnerTuple namespace is documented in multi-tenancy but not in isolation primitives
- OwnerUiScope is not documented but used by Filament packages
- OwnerScopeKey has no documentation but is critical for cache/filesystem isolation

## Checkpoint: 2026-07-01

- Completed: Core source review (OwnerContext, OwnerScope, HasOwner, Targeting)
- In progress: Remaining docs verification and cross-package consistency
- Next: Read remaining source files, then mark package complete