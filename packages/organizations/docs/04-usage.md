---
title: Organizations Usage
---

## Create and transfer ownership

```php
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Actions\TransferOrganizationOwnershipAction;

$organization = CreateOrganizationAction::make()->handle($user, [
    'name' => 'Knowledge Circle',
]);

TransferOrganizationOwnershipAction::make()->handle(
    $organization,
    $currentOwner,
    $newOwner,
);
```

`created_by` records the founding user and is immutable. Ownership is the
`Owner` role on the membership pivot and moves only through the transfer
action; transferring ownership never rewrites `created_by`. The new owner
must already be a member, and the action locks the owner set while changing
both roles.

Use the supplied lifecycle actions for public/private transitions and
active/suspended/archived transitions. Use membership actions for all member
writes so the organization ownership guard is applied.

Adding a member resolves a persisted user through the membership relation and
rejects an unknown or forged user ID. Invitations intentionally accept an
email address for an unregistered invitee; the invitation workflow, rather
than member attachment, creates the later membership.

## Current context

The middleware resolves the organization through the configured resolver and
wraps the request in `OwnerContext`. It never treats a client-provided ID as
trusted membership; the application resolver must verify the actor is a
member.

The shipped default requires context. Public or intentionally global routes
must pass `required:false` explicitly and should enter an explicit global
owner context in the handler.

## Lifecycle and audit hook

Archive, restore, suspend, public, private, and ownership-transfer workflows
are exposed as core actions. Each transition is centralized through the
organization state-transition service and invokes `OrganizationLifecycleHook`;
bind that hook to the host activity logger when lifecycle audit records are
required.

Restoring an organization makes its status active but retains terminal
timestamps as historical facts. Use `isActive()` together with the lifecycle
timestamps when presenting current state.
