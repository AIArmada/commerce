---
title: Filament Referrals Usage
---

# Filament Referrals Usage

## Programs

The program resource supports list, create, edit, and view pages. Creation runs through the core CreateReferralProgram Action and edits run through the core UpdateReferralProgram Action, so both paths enforce the same rules: a currency that is not a three-letter code, a window that ends before it starts, or a name that slugifies to nothing is refused and the stored program is left unchanged. Each reward side is mapped to and from its frozen core specification; an empty side is stored as null.

The status field is read-only on the edit page and is withheld from the submitted payload, so an archived program cannot be reactivated from the form. Status moves only through the guarded activate, pause, and archive actions on the list, each of which re-checks the allowed transitions before writing. The list includes invite and reward counts, date windows, and a status filter.

The reward specification fields offer only drivers enabled by `referrals.fulfillment.drivers.<driver>.enabled`. Options are resolved at runtime, and Filament rejects submitted driver values that are currently disabled on both create and edit. The rewards list retains all known drivers in its filter, so historical reward rows remain readable and filterable after a driver is disabled.

When a side's driver is `reward_points`, the form also carries the **Rewards family key** field: an optional 1..64 slug that binds that side's points promise to the Rewards program family allowed to fulfil it. The core validator enforces the slug shape, keeps the field on the points driver only, and rejects two points sides with different amounts unless each side names its own family.

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
