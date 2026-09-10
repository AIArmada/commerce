---
title: Membership Troubleshooting
---

## Role Is Not Scoped to the Subject

Enable Spatie teams and `membership.features.team_scoped_roles`.

Owner scoping is configured under `membership.owner`. The old nested owner
key is not read.

## Invitation Cannot Be Accepted

Confirm the invitation is pending, unexpired, and its normalized email matches the accepting user's email.

## Pivot Table Is Missing

Create the host application's pivot migration as shown in
[Usage: Pivot migration](04-usage.md#pivot-migration).
