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

Use the supplied lifecycle actions for public/private transitions and
active/suspended/archived transitions. Use membership actions for all member
writes so the organization ownership guard is applied.

## Current context

The middleware resolves the organization through the configured resolver and
wraps the request in `OwnerContext`. It never treats a client-provided ID as
trusted membership; the application resolver must verify the actor is a
member.
