---
title: Membership Context
package: membership
status: current
surface: core
family: foundation
keywords:
  - membership
  - invitation
  - application
  - member-role
---

# Membership Context

## Snapshot
- Composer: `aiarmada/membership`
- Role: Polymorphic membership: applications, invitations, member pivots, Spatie role sync for any subject.
- Triggers: membership, invitation, application, member-role
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `authz`, `commerce-support`, `organizations`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts when the change crosses boundaries
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Join/invite flows for any model.
- Skip when: Org aggregate itself — see organizations; permissions — see authz.
- Owner/security: Owner-scoped (both models).

## Key surfaces
- Models: `MembershipApplication`, `MembershipInvitation`
- Actions/Services: `Actions/AcceptInvitationAction`, `Actions/AddMemberAction`, `Actions/ApplyForMembershipAction`, `Actions/ApproveMembershipApplicationAction`, `Actions/CancelMembershipApplicationAction`, `Actions/ChangeMemberRoleAction`, `Actions/InviteMemberAction`, `Actions/RejectMembershipApplicationAction`
- Config `membership.php`: `database`, `json_column_type`, `tables`, `applications`, `invitations`, `invitations`, `token_length`, `hash_tokens`, `default_expiry_days`, `pivot`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
