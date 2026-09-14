---
title: Membership Troubleshooting
---

## Role Is Not Scoped to the Subject

Enable Spatie teams and `membership.features.team_scoped_roles`.

Owner scoping is configured under `membership.owner`. The old nested owner
key is not read.

## Invitation Cannot Be Accepted

Confirm the invitation is pending, unexpired, and its normalized email matches the accepting user's email. When the raw credential is passed to `AcceptInvitationAction`, confirm it matches the invitation as well.

## Re-invite Returns a Stale Invitation

Re-inviting the same subject, email, and role returns the existing pending invitation without a new event. If that invitation is past its expiry deadline, it is transitioned to `expired` and a fresh invitation is created instead. Run `php artisan membership:expire-invitations` to persist expiry for reporting without waiting for a re-invite.

## Pivot Table Is Missing

Create the host application's pivot migration as shown in
[Usage: Pivot migration](04-usage.md#pivot-migration).
