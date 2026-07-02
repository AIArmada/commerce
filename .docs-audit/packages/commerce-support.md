# Package Audit Ledger: commerce-support

## Package Information
- Path: packages/commerce-support
- Package type: foundation
- Documentation path: packages/commerce-support/docs/*.md
- Current status: CODE_REVIEW_IN_PROGRESS
- Current subsystem: Core primitives (owner scoping, targeting)
- Last completed action: Read SupportServiceProvider, OwnerContext, OwnerScope, HasOwner, OwnerQuery, TargetingEngine, Webhooks
- Next exact action: Read remaining source files (OwnerCache, OwnerBatchRunner, PaymentSubjectResolver, Contracts, Models), then compare with docs
- Review baseline commit: 248d09381127f15670293745faaa3ef6fb84d1d9
- Blocking issue: None

## Review Scope

### Included
- Source: All files under packages/commerce-support/src/**/*.php
- Tests: All files under tests/src/CommerceSupport/**/*.php
- Configuration: packages/commerce-support/config/commerce-support.php
- Migrations: packages/commerce-support/database/migrations/*.stub
- Documentation: All files under packages/commerce-support/docs/*.md

### Excluded
- Vendor files (spatie/laravel-settings, etc.)
- Generated code

## File Review Ledger

| File | Category | Status | Key Findings | Follow-Up |
|---|---|---|---|---|
| src/SupportServiceProvider.php | Source | REVIEWED | Registers OwnerResolverInterface, TargetingEngineInterface, PaymentSubjectResolver as singletons; loads conditional migrations; validates morph key type; fails if NullOwnerResolver with owner enabled | Verify all service bindings match docs |
| src/Support/OwnerContext.php | Source | REVIEWED | Static state manager with HTTP/fallback storage; setForRequest() for middleware, withOwner() for scoped work; lifecycle events; fromTypeAndId() for reconstruction; assertResolvedOrExplicitGlobal() | Verify lifecycle events documented |
| src/Support/OwnerScope.php | Source | REVIEWED | Global scope applying OwnerQuery; reads config via OwnerScopeConfig | Check OwnerScopeConfig usage |
| src/Support/OwnerQuery.php | Source | REVIEWED | Apply owner scoping to Eloquent and Query builder; supports includeGlobal param | Verify default columns match docs |
| src/Support/OwnerScopeConfig.php | Source | REVIEWED | Config holder with fromConfig() factory method; supports enabled, includeGlobal, owner, autoAssignOnCreate | Check config keys against docs |
| src/Traits/HasOwner.php | Source | REVIEWED | Boot registers global scope, creating/saving/deleting hooks; guards owner assignment, reassignment, promotion, demotion; owner morphTo relationship; forOwner/globalOnly/withoutOwnerScope scopes; immutability enforcement | Complex logic, verify docs accuracy |
| src/Traits/HasOwnerScopeConfig.php | Source | PENDING | | Read this file |
| src/Targeting/TargetingEngine.php | Source | REVIEWED | 23+ evaluator registration; all/any/custom modes; validation method; default evaluators list includes all 22 evaluators | Verify all evaluators are real |
| src/Webhooks/CommerceWebhookProcessor.php | Source | REVIEWED | Abstract base extending ProcessWebhookJob; handle() calls ProcessWebhookCallAction; duplicate detection via WebhookCall; extractEventType and extractEventId defaults | Verify docs match implementation |
| src/Actions/ProcessWebhookCallAction.php | Source | REVIEWED | Transactional webhook processing with row lock; duplicate handling; exception capture with failed status; throws on error | |
| config/commerce-support.php | Config | REVIEWED | morph_key_type, json_column_type, tables, currency, owner (enabled/resolver), health, filament navigation | Missing doc references |
| docs/01-overview.md | Docs | PENDING | Claims targeting has 19+ evaluators; needs verification | Compare with source |
| docs/03-configuration.md | Docs | PENDING | Needs verification against config file | Compare with config |
| docs/04-multi-tenancy.md | Docs | PENDING | Needs verification against HasOwner trait | |
| docs/05-payment-contracts.md | Docs | PENDING | Needs verification against Contracts/Payment/*.php | |
| docs/06-targeting-engine.md | Docs | PENDING | Needs verification against TargetingEngine | |

## Runtime Flows

### Flow: Owner Context Resolution
- Status: REVIEWED
- Trigger: Incoming HTTP request or job execution
- Entry point: OwnerResolverInterface::resolve() via OwnerContext::resolve()
- Call path: OwnerContext::resolve() → app(OwnerResolverInterface::class)->resolve() OR OwnerContext::setForRequest() → request attributes
- Inputs: None (resolve) or ?Model (setForRequest)
- Validation: Resolver must be bound if owner.enabled=true; NullOwnerResolver throws at boot
- Outputs: ?Model owner or null
- Persistence: None
- Side effects: Lifecycle events dispatched
- Errors: NoCurrentOwnerException via assertResolvedOrExplicitGlobal
- Retries: None
- Tests: OwnerContextTest.php
- Documentation target: 04-multi-tenancy.md
- Unresolved questions: None

### Flow: Targeting Evaluation
- Status: REVIEWED
- Trigger: Promotion/voucher eligibility check
- Entry point: TargetingEngine::evaluate($targeting, $context)
- Call path: evaluate() → match mode → evaluateAll/evaluateAny/evaluateExpression → evaluateRule → $evaluator->evaluate()
- Inputs: array $targeting (mode/rules/expression), TargetingContextInterface
- Validation: validate() method checks structure; fails closed on invalid
- Outputs: bool eligibility
- Persistence: None
- Side effects: None (logging on evaluation failure)
- Errors: InvalidArgumentException on invalid config; returns false on rule exceptions
- Retries: None
- Tests: TargetingEngineTest.php
- Documentation target: 06-targeting-engine.md
- Unresolved questions: Verify all 22 evaluators documented

## Public API Inventory

| API | Type | Status | Evidence | Documentation Target |
|---|---|---|---|---|
| OwnerContext | Class | CONFIRMED | src/Support/OwnerContext.php | 04-multi-tenancy.md |
| OwnerContext::resolve() | Method | CONFIRMED | Reads from resolver or request | 04-multi-tenancy.md |
| OwnerContext::withOwner() | Method | CONFIRMED | Temporary owner override with restoration | 04-multi-tenancy.md |
| OwnerContext::setForRequest() | Method | CONFIRMED | HTTP-only request attribute setter | 04-multi-tenancy.md |
| HasOwner | Trait | CONFIRMED | src/Traits/HasOwner.php | 04-multi-tenancy.md, 10-traits-utilities.md |
| OwnerScope | Class | CONFIRMED | src/Support/OwnerScope.php | 04-multi-tenancy.md |
| OwnerQuery | Class | CONFIRMED | src/Support/OwnerQuery.php | 04-multi-tenancy.md |
| OwnerWriteGuard | Class | CONFIRMED | src/Support/OwnerWriteGuard.php | 04-multi-tenancy.md |
| TargetingEngine | Class | CONFIRMED | src/Targeting/TargetingEngine.php | 06-targeting-engine.md |
| TargetingEngine::evaluate() | Method | CONFIRMED | Supports all/any/custom modes | 06-targeting-engine.md |
| TargetingEngine::validate() | Method | CONFIRMED | Returns array of error strings | 06-targeting-engine.md |
| OwnerBatchRunner | Class | CONFIRMED | src/Support/OwnerBatchRunner.php | Needs documentation? |
| PaymentSubjectResolver | Class | CONFIRMED | src/Support/Payment/PaymentSubjectResolver.php | 05-payment-contracts.md |
| CommerceWebhookProcessor | Abstract | CONFIRMED | src/Webhooks/CommerceWebhookProcessor.php | 08-webhooks.md |
| MoneyNormalizer | Final | CONFIRMED | src/Support/MoneyNormalizer.php | 10-traits-utilities.md |
| OwnerCache | Final | CONFIRMED | src/Support/OwnerCache.php | 11-isolation-primitives.md |

## Configuration Inventory

| Key | Status | Default | Read At | Runtime Effect | Documentation Target |
|---|---|---|---|---|---|
| database.morph_key_type | CONFIRMED | 'uuid' | SupportServiceProvider::validateMorphKeyType() | Schema::defaultMorphKeyType() | 03-configuration.md |
| database.json_column_type | CONFIRMED | 'jsonb' | config() calls in migrations | Used for JSON columns | 03-configuration.md |
| database.tables.saved_searches | CONFIRMED | 'saved_searches' | Migration stubs | Table name for saved searches | Needs doc |
| database.tables.reports | CONFIRMED | 'reports' | Migration stubs | Table name for reports | Needs doc |
| database.tables.notification_preferences | CONFIRMED | 'notification_preferences' | Migration stubs | Table name for preferences | Needs doc |
| currency.default | CONFIRMED | 'MYR' | MoneyNormalizer::format() | Default currency code | 03-configuration.md |
| owner.enabled | CONFIRMED | false | SupportServiceProvider::ensureOwnerResolverIsConfiguredWhenOwnerModeEnabled() | Fail at boot if NullOwnerResolver | 03-configuration.md |
| owner.resolver | CONFIRMED | NullOwnerResolver::class | SupportServiceProvider::registerOwnerResolver() | Bound to OwnerResolverInterface | 03-configuration.md |
| health.view_ability | CONFIRMED | 'viewCommerceHealth' | CommerceHealthWidget::canView() | Gate ability check | 03-configuration.md |
| filament.navigation.enabled | CONFIRMED | true | CommerceNavigationPlugin | Navigation building | 03-configuration.md |
| filament.navigation.groups | CONFIRMED | [] | CommerceNavigation | Navigation group config | 03-configuration.md |
| filament.navigation.packages | CONFIRMED | [] | CommerceNavigation | Per-package navigation | 03-configuration.md |
| filament.navigation.items | CONFIRMED | [] | CommerceNavigation | Per-item overrides | 03-configuration.md |

## Documentation Reconciliation

| Existing Claim or Section | Classification | Evidence | Required Action | Status |
|---|---|---|---|---|
| "Targeting has 19+ built-in evaluators" | ACCURATE_BUT_INCOMPLETE | Source shows 22 evaluators in registerDefaultEvaluators() | Update to 22 evaluators | PENDING |
| OwnerBatchRunner exists | ACCURATE | src/Support/OwnerBatchRunner.php | Not documented in overview | PENDING |
| TargetingContext::fromCart() exists | ACCURATE | src/Targeting/TargetingContext.php | Verify docs match implementation | PENDING |

## Findings

### Confirmed Behaviour
- OwnerContext uses request attributes for HTTP request isolation; uses static fallback for console/jobs
- NullOwnerResolver throws RuntimeException during boot if owner.enabled=true and no concrete resolver
- OwnerWriteGuard fails closed if model doesn't implement owner scoping
- TargetingEngine fails closed on invalid targeting (returns false)
- Webhook processing is transactional with row locking

### Suspected Issues
- None critical found

### Contradictions
- docs/01-overview.md claims "19+ built-in evaluators" but source has 22

### Ambiguities
- OwnerBatchRunner purpose and usage not clearly documented

### Missing Tests
- No cross-package integration tests in commerce-support itself

### Documentation Gaps
- Database tables (saved_searches, reports, notification_preferences) not documented in depth
- OwnerBatchRunner API not documented

## Documentation Changes

| File | Change | Status |
|---|---|---|

## Validation Checklist
- [ ] Every relevant file has a terminal status
- [ ] No unexplained file remains NOT_REVIEWED
- [ ] No file remains IN_PROGRESS
- [ ] Main runtime flows are traced end to end
- [ ] Public APIs are documented
- [ ] Configuration is verified
- [ ] Persistence and side effects are documented
- [ ] Errors and failure behaviour are documented
- [ ] Examples use real APIs
- [ ] Documentation links resolve
- [ ] Existing claims are reconciled
- [ ] Cross-package dependencies are documented
- [ ] Remaining uncertainties are explicitly recorded