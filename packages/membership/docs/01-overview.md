---
title: Membership Overview
---

## Purpose

`aiarmada/membership` adds reusable membership workflows to any Eloquent subject:

- Membership applications with approval, rejection, and cancellation
- Email invitations with hashed token storage
- Subject-member pivot management
- Team-scoped Spatie role synchronization for `Owner`, `Admin`, `Editor`, and `Viewer`
- Configurable permission sets per role (`role_permissions`), including full access for owners
- Events, notifier contracts, and membership hooks

## What this package owns

- Models `MembershipApplication`, `MembershipInvitation` (both `HasOwner`-scoped), and the host-extensible `MembershipPivot`
- Actions `ApplyForMembership`, `Approve/Reject/CancelMembershipApplication`, `InviteMember`, `AcceptInvitation`, `RevokeInvitation`, `AddMember`, `RemoveMember`, `ChangeMemberRole`
- `Services\MembershipRoleSyncService` — pivot → Spatie team-role sync
- Config `membership.php`: `database`, `invitations` (token length and expiry), `pivot`, `role_mapping`, `role_permissions`, `owner`, `features`

## What this package does not own

- The organization/tenant aggregate itself — see `aiarmada/organizations`
- Permission definitions and wildcard resolution — see `aiarmada/authz`
- Filament admin UI (there is no `filament-membership`; org UI lives in `filament-organizations`)

## Role synchronization policy

`MembershipRoleSyncService` is the bridge between the domain `MemberRole` and
the mapped Spatie role. Membership owns the mapped role assignment for a
member in a subject scope; `authz` owns the role and permission APIs.

- `syncAll()` and `membership:sync-roles` reconcile additively by default:
  missing mapped roles and configured permissions are created, while existing
  permissions and all user role assignments are retained.
- Passing `--prune` makes the configured permission list exact for mapped
  membership roles. It removes unconfigured permissions from those roles, but
  never deletes role assignments from users.
- `AddMemberAction`, `ChangeMemberRoleAction`, and `RemoveMemberAction` update
  the member pivot and the mapped authz assignment in the same transaction.
  Assignment is additive for unrelated roles. A role change or removal revokes
  the old mapped role only in the current subject team; assignments in other
  teams remain.
- Authz has no provenance column for a role assignment. A direct grant that
  uses the same mapped role name in the same subject team is therefore
  indistinguishable from the membership grant and is treated as membership
  owned. Use a different role name for an independent grant.

When team-scoped roles are enabled, the subject key is the authz team id. The
service verifies that the resolver accepted that id and restores the previous
team context after every assignment or revocation.
