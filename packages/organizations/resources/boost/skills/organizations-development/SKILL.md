---
name: organizations-development
description: Develop owner-safe organizations integrations following Laravel Dune boundaries.
---

# Organizations

Organizations owns organization lifecycle and ownership invariants. Invoke CreateOrganizationAction, UpdateOrganizationAction, ArchiveOrganizationAction, RestoreOrganizationAction, SuspendOrganizationAction, MakeOrganizationPublicAction, MakeOrganizationPrivateAction and TransferOrganizationOwnershipAction. Resolve actor and organization explicitly; lifecycle mutations authorize under a row lock. The current.organization middleware alias establishes OwnerContext. Use AddOrganizationMemberAction, ChangeOrganizationMemberRoleAction, RemoveOrganizationMemberAction, InviteOrganizationMemberAction and RevokeOrganizationInvitationAction for actor-authorized organization membership mutations; only the ownership-transfer workflow may replace the owner.

## Verification

From the Commerce monorepo root, run `composer --working-dir=packages/organizations arch:check`, scoped Pest, PHPStan and Pint. Update callers and tests when moving APIs; remove superseded code in the same change.
