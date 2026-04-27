---
title: Commerce Support Audit - 2026-04-27
---

# Commerce Support Audit - 2026-04-27

## Scope

This audit covers `packages/commerce-support` as the shared foundation for Commerce packages, plus representative consumer-package checks where support primitives are commonly reused.

Included areas:

- owner context, owner resolver binding, global scopes, write guards, route binding, and query-builder helpers,
- targeting engine validation/evaluation,
- webhook signature validation and processing bases,
- health check base behavior,
- payment contracts and status transitions,
- audit/activity traits,
- install/publish commands,
- package documentation and reusable consumer audit guidance.

Consumer package remediation is intentionally out of scope for this pass. Consumer findings should be handled with the companion audit standard in `commerce-support-consumer-audit-standard.md`.

## Executive summary

| Severity | Finding | Status |
| --- | --- | --- |
| High | `commerce-support.owner.enabled` was read by the provider but missing from config | Fixed |
| High | `NullOwnerResolver` only warned when owner mode was enabled | Fixed |
| High | Targeting rules failed open for unknown or malformed rules | Fixed |
| Medium | Support PHPUnit XML missed `tests/src/CommerceSupport` | Fixed |
| Medium | Webhook and health base classes lacked direct tests | Fixed |
| Medium | `CommerceInstallCommand` duplicated the registered `InstallCommand` signature | Fixed |
| Medium | Targeting and webhook docs had stale API examples | Fixed |
| Low | Consumer packages use mixed owner-scoping patterns | Follow-up |

## Fixed findings

### High: owner mode now fails closed without a real resolver

**Files:**

- `packages/commerce-support/config/commerce-support.php`
- `packages/commerce-support/src/SupportServiceProvider.php`
- `tests/src/Support/SupportServiceProviderTest.php`

`SupportServiceProvider` already inspected `commerce-support.owner.enabled`, but the published config did not define it. When owner mode was enabled with the default `NullOwnerResolver`, the provider logged a warning instead of stopping boot. That created a dangerous false sense of tenant isolation.

The provider now fails closed when `commerce-support.owner.enabled=true` and the resolved `OwnerResolverInterface` implementation is `NullOwnerResolver`. Single-tenant installs remain supported by leaving global owner mode disabled.

### High: targeting no longer treats unknown rules as eligible

**Files:**

- `packages/commerce-support/src/Targeting/TargetingEngine.php`
- `packages/commerce-support/src/Targeting/Enums/TargetingRuleType.php`
- `tests/src/Support/TargetingEngineTest.php`

Unknown rule types, missing rule types, invalid modes, empty custom expressions, malformed nested expressions, and rules without registered evaluators now fail closed. Empty targeting remains explicitly eligible to represent “no restrictions”.

The rule-type enum now includes evaluator-backed rule types that existed in code but were not represented in validation: `product_quantity`, `payment_method`, `coupon_usage_limit`, and `referral_source`.

### Medium: support test suite now includes both support test roots

**File:** `.xml/commerce-support.xml`

The XML suite now includes both:

- `tests/src/Support`
- `tests/src/CommerceSupport`

This keeps package-level coverage aligned with the current test layout.

### Medium: webhook and health bases now have direct coverage

**Files:**

- `tests/src/Support/WebhooksTest.php`
- `tests/src/Support/CommerceHealthCheckTest.php`

Coverage now verifies signature validation, unsigned/invalid webhook rejection, webhook processor event extraction and `processed_at` marking, default webhook profile behavior, health check result pass-through, exception-to-failed conversion, and metadata helpers.

### Medium: duplicate command removed

**File removed:** `packages/commerce-support/src/Commands/CommerceInstallCommand.php`

The duplicate command had the same `commerce:install` signature as `InstallCommand` but was not registered by `SupportServiceProvider`. The registered `InstallCommand` remains the canonical implementation and is still covered by `tests/src/Support/Feature/CommerceInstallCommandTest.php`.

## Representative consumer findings

These were observed during read-only representative checks and should be handled package-by-package using `commerce-support-consumer-audit-standard.md`.

### Raw query builder owner-scope bypass risk

`DB::table()` calls bypass Eloquent global scopes. Any raw query touching tenant-owned tables must use `OwnerQuery::applyToQueryBuilder()` or an equivalent explicit owner predicate.

Representative areas to inspect first:

- affiliate analytics/reporting services,
- promotions pivot queries,
- widgets or reports using aggregate query builders.

### Sparse submitted-ID validation in Filament actions

Some Filament resources scope list queries but do not revalidate submitted foreign IDs inside action handlers. Form option scoping is not a security boundary. Submitted IDs should be resolved with `OwnerWriteGuard::findOrFailForOwner()` or a package-specific helper that delegates to it.

### Non-request surfaces need explicit owner context

Commands, jobs, scheduled tasks, exports, reports, health checks, and webhook processors must not rely on ambient web auth. They should pass or iterate owners explicitly and wrap work in `OwnerContext::withOwner($owner, ...)`.

### Consumer docs should copy current support semantics

Package docs should describe:

- global owner mode as the resolver safety switch,
- per-package owner flags as model/query scoping flags,
- explicit global context for global-row writes,
- fail-closed targeting validation,
- concrete webhook validators/processors rather than abstract bases.

## Verification performed

- `./vendor/bin/pest tests/src/Support --parallel` — passed after implementation.

Final verification for this change set should also include:

- `./vendor/bin/pest tests/src/CommerceSupport --parallel`
- `./vendor/bin/phpstan analyse packages/commerce-support/src --level=6`
- targeted consumer tests if package-level targeting or webhook behavior is changed later.
