---
title: Filament Organizations Usage
---

## Resource surfaces

The adapter provides organization list, create, view, and edit pages, plus
members and invitations relation managers. Ownership transfer and lifecycle
actions call the core package actions.

## Security boundary

Filament is not the tenancy security boundary. The resource query is scoped to
organizations where the authenticated user is a member, and custom action
handlers delegate to the core authorization contract and owner-safe actions.
Applications must keep the same rule in any extensions they add.

Member lookup by email is normalized (trimmed, case-insensitive) and reports a
generic failure so responses never reveal whether an email belongs to a user.
Pending invitations can be revoked from the invitations table; revoke
re-checks organization membership and the `organization.manage-members`
ability before delegating to the membership action. Ownership transfer lists
members through a capped async search and re-validates membership on submit.

## Member and invitation actions

Relation action handlers receive the relation manager as `$livewire` and
resolve the organization via `getOwnerRecord()`; type-hinting the
organization model directly is not injectable and throws at runtime.
