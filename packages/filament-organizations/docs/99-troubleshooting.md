---
title: Filament Organizations Troubleshooting
---

## Records are not visible

Confirm the authenticated panel user has an `organization_members` row for the
organization. Resource visibility is membership-scoped by design.

## An action is rejected

The core organization authorization contract is authoritative. Configure an
application implementation of that contract when roles or abilities differ
from the default Owner/Admin policy.

## Do not add product fields here

Extend the resource in the application for addresses, media, events,
moderation, translations, or product-specific permissions.
