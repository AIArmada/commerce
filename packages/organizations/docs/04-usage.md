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

## Current context

The middleware resolves the organization through the configured resolver and
wraps the request in `OwnerContext`. It never treats a client-provided ID as
trusted membership; the application resolver must verify the actor is a
member.

The shipped default requires context. Public or intentionally global routes
must pass `required:false` explicitly and should enter an explicit global
owner context in the handler.
