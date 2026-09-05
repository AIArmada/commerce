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

- Models `MembershipApplication`, `MembershipInvitation` (both `HasOwner`-scoped)
- Actions `ApplyForMembership`, `Approve/Reject/CancelMembershipApplication`, `InviteMember`, `AcceptInvitation`, `RevokeInvitation`, `AddMember`, `RemoveMember`, `ChangeMemberRole`
- `Services\MembershipRoleSyncService` — pivot → Spatie team-role sync
- Config `membership.php`: `database`, `invitations` (token length, hashing, expiry), `pivot`, `role_mapping`, `role_permissions`, `features`

## What this package does not own

- The organization/tenant aggregate itself — see `aiarmada/organizations`
- Permission definitions and wildcard resolution — see `aiarmada/authz`
- Filament admin UI (there is no `filament-membership`; org UI lives in `filament-organizations`)
