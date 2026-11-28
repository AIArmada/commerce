---
name: membership-development
description: Develop membership integrations using Laravel Dune package boundaries.
---

# Membership

Membership owns applications, invitations and membership-role reconciliation. Call `AIArmada\Membership\Actions\AddMemberAction::run()`, `RemoveMemberAction::run()`, `ChangeMemberRoleAction::run()` and `SyncMembershipRolesAction` through the container. The subject must expose a belongs-to-many members relationship; use the `HasMembers` trait. Resolve owner scope before mutation, serialize changes under the subject row lock, and preserve the host subject's membership invariants. Invitation delivery occurs after commit. Do not add compatibility aliases for moved classes.

## Verification

From the monorepo root, run `composer --working-dir=packages/membership arch:check`, affected Pest tests, PHPStan and Pint.
