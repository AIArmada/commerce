---
title: Membership Troubleshooting
---

## Role Is Not Scoped to the Subject

Enable Spatie teams and `membership.features.team_scoped_roles`.

Owner scoping is configured under `membership.owner`. The old nested owner
key is not read.

## Invitation Cannot Be Accepted

Confirm the invitation is pending and unexpired. Acceptance is recipient-bound
per channel: the email verifier requires the accepting user's normalized email
to match the stored recipient, and a non-email channel requires its registered
`InvitationRecipientVerifier` to confirm the accepting user owns the recipient
identity. When the raw credential is passed to `AcceptInvitationAction`,
confirm it matches the invitation as well. A failure surfaces as
`Invitation recipient identity could not be verified.` or
`Invitation token is invalid.`

If a non-email channel fails with the identity error, register an
`InvitationRecipientVerifier` for that channel in
`membership.invitations.verifiers` (or via
`InvitationChannelManager::registerVerifier()`). Channels without a verifier
always reject acceptance.

## Invitation Channel Is Not Registered

`InviteMemberAction` throws `The invitation channel [name] is not registered.`
Register the channel implementation in `membership.invitations.channels` or
via `InvitationChannelManager::registerChannel()`. The package never falls
back to email for an unknown channel.

## Invalid Recipient for a Channel

Each channel normalizes its own identifier. Email rejects malformed or
over-length addresses; an SMS integration typically requires E.164 format.
An invalid recipient throws before any invitation row is written.

## Re-invite Returns a Stale Invitation

Re-inviting the same subject, channel, recipient, and role returns the existing pending invitation without a new event. If that invitation is past its expiry deadline, it is transitioned to `expired` and a fresh invitation is created instead. To persist expiry for reporting without waiting for a re-invite, call `$invitation->expireIfDue()` on the past-due rows.

## Pivot Table Is Missing

Create the host application's pivot migration as shown in
[Usage: Pivot migration](04-usage.md#pivot-migration).
