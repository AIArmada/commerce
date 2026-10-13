---
title: Filament Referrals Usage
---

# Filament Referrals Usage

## Programs

The program resource supports list, create, edit, and view pages. Creation runs through the core CreateReferralProgram Action. Each reward side is mapped to and from its frozen core specification; an empty side is stored as null. Edits re-resolve the program with ReferralProgramGuard before saving the editable fields. The list includes invite and reward counts, date windows, a status filter, and guarded activate, pause, and archive actions.

The program view contains a read-only invites relation manager.

## Invites

Invites are list and view only. They are created through the core CreateReferralInvite Action so the plaintext token is returned once to the host. The resource can revoke pending or accepted invites through RevokeReferralInvite.

The admin screens show a shortened invitee hash and presenter labels for referrer and accepted-by models. They never render token_hash or decrypt the addressed invitee contact. The invite view includes its qualification business key and lifecycle timestamps, with a rewards relation manager.

## Rewards

Rewards are list and view only. The fulfilment action is available for pending or failed rows and calls FulfillReferralReward. The reversal action is available for fulfilled rows, requires a reason, and calls ReverseReferralReward. Both actions resolve the reward through ReferralProgramGuard again when submitted and report the core result in a Filament notification.

The reward list shows the program, shortened invite ID, side, beneficiary label, driver, formatted minor-unit amount or points, status, external reference, and fulfilment/reversal timestamps.

## Owner scoping

ReferralProgram carries the core OwnerScope. Invite and reward tables, relation managers, program filters, and all four widget counts constrain their queries to IDs returned by ReferralFilamentScope::scopedProgramIds(). Filament filtering is presentation only; mutation handlers revalidate the program boundary through core Actions or ReferralProgramGuard.

## Statistics

ReferralStatsWidget reports pending, qualified, and rewarded invite counts plus rewards awaiting fulfilment. Failed rewards remain in the pending fulfilment count so operators can retry them.

See the core [referrals usage guide](../../referrals/docs/04-usage.md) for invite creation, qualification, fulfilment rails, and reversal semantics.
