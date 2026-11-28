---
name: filament-rewards-development
description: Develop filament-rewards integrations using Laravel Dune package boundaries.
---

# FilamentRewards

Filament Rewards is the administrative entry point for Rewards. Read program-scoped records through `AIArmada\FilamentRewards\Queries\RewardProgramScopeQuery`; delegate durable mutations to Rewards Actions. A staff role or explicit `reward-staff` Gate for users without role methods is required on all resource list and record pages, in addition to normal Filament authorization. Use `RewardAdminPresenter::staffActor()` to require a valid authentication identifier and `getAuthIdentifier()` for staff attribution, rather than assuming the actor is an Eloquent model. Fraud stewardship requires both staff access and the `reward-fraud-steward` ability. Recheck staff access and applicable operation-specific abilities inside handlers, including read-only qualification previews, not only in button visibility. When verifying a pending payee, pass its registration identity in `verified_from_registration_id`; stale or mismatched registrations must be refused. Never widen tenant scope for a record mutation. Do not add compatibility aliases.

## Verification

From the monorepo root, run `composer --working-dir=packages/filament-rewards arch:check`, affected Pest tests, PHPStan and Pint.
