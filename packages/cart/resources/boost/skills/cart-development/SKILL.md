---
name: cart-development
description: Develop and integrate the Cart package with owner-safe domain operations.
---

# Cart development

Cart's durable resources are owned by the LiveCart, Conditions and Snapshots features. GuestMigration owns guest-to-user orchestration. Use the Cart facade/manager and storage contract for live cart operations; never write normalized snapshots as a substitute for mutating the live cart. Use the owning feature actions for stored-condition administration and checkout lifecycle. Preserve exact owner tuples, CAS revisions, integer minor-unit money and immutable buyable quotes. Never use a normalized read model as the authoritative checkout price.

## Verification

Run the package architecture checks and the affected monorepo Pest tests, PHPStan and Pint. Update callers directly when APIs change; do not add compatibility aliases or backfill migrations.

### Runtime extension interfaces

The pure `AIArmada\\Cart\\Contracts` namespace is reserved for data-oriented interfaces and immutable values. Laravel-facing cart extension interfaces that accept a mutable cart, cart item, condition object, or Eloquent owner model live in `AIArmada\\Cart\\Runtime`: `CartManagerInterface`, `CartSnapshotSyncInterface`, `CartValidatorInterface`, `CartConditionConvertible`, and `ConditionProviderInterface`. Import these directly from Runtime.
