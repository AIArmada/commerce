---
title: Rewards troubleshooting
---

# Rewards troubleshooting

## Evaluation refuses everything with `outcome_not_allowlisted`

The `rewards.outcomes` registry is empty or does not contain the fact outcome. Register the host's outcomes in `config/rewards.php` (`outcomes`), then make sure the program version's rule outcomes reference them.

## Evaluation refuses with recipient-type errors

`rewards.recipient_types` is empty. Register the host's recipient morph types (for example `['user']`).

## Cash approval reports `funding-adapter-missing`

The default readiness checkers are null objects. Either bind the funded checkers (`FundedRewardReadinessChecker`, `FundedRewardFinancialReadinessChecker`) or record funding and verify a payee first — see [usage](04-usage.md).

## Coupon fulfillment throws about a missing binding

Coupon benefits require a bound `RewardBenefitCouponFulfillment`. Non-coupon (manual handoff) benefits are unaffected. Bind a coupon adapter to enable coupon-type items.

## Payout submission reports a foreign operation identity

The bound executor returned external ids that disagree with the stored ones for the same request. This is a rail bug, not a retryable state: fix the executor, then reconcile the request.

## Corrections sweep reports nothing

Without `--keys`, the sweep reads the bound `RewardCorrectionKeySource`. The default source yields no keys. Pass `--keys` or bind a source that reads the host's correction store.

## Cross-tenant writes throw `AuthorizationException`

Inbound program/award/request/grant/enrollment/recovery/contribution ids are validated against the current owner scope by `RewardProgramGuard`. Wrap host batch work in `OwnerContext::withOwner($owner, ...)` per owner. Member-role actors cannot claw back award points, settle or write off recoveries, leave or reaccept enrollments, or invalidate milestone contributions — those paths require a staff/admin/system identity on top of the owner scope. With `owner.enabled=false` the guard degrades to a plain existence lookup, so single-tenant hosts are unchanged.

## System runs throw `NoCurrentOwnerException`

Queue workers and cron resolve no ambient owner. Rewards' own system paths
(`EvaluateRewardEntitlementJob`, `RecheckRewardAwardsJob`, the corrections
sweep and its command) enter each program's owner themselves — they do not
need — and must not be wrapped in — an ambient owner. If a custom host job
calls a core action without any owner context, wrap the call per program:

```php
use AIArmada\Rewards\Support\RewardProgramGuard;

RewardProgramGuard::withProgramOwner($programId, static fn () => $action->handle(...));
```

`RewardProgramGuard::withProgramOwner()` throws
`InvalidArgumentException` for an unknown program id or a program whose
owner row was deleted; the queue then retries/dead-letters instead of
silently skipping the work.
