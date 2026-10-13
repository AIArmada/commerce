---
title: Referrals Context
package: referrals
status: current
surface: domain
family: growth-and-incentives
keywords:
  - referral
  - referrals
  - member-get-member
  - invite
  - qualification
  - referral-reward
---

# Referrals Context

## Snapshot
- Composer: `aiarmada/referrals`
- Role: Owner-scoped member-get-member programs, addressed invites, host-reported qualification, frozen two-sided reward intents, fulfilment, and reversal.
- Triggers: member-get-member, referral invite, invite claim, referral qualification, referral reward
- Search first: `src/Actions, src/Models, src/Support, src/Fulfillment, config, docs`
- Related: `commerce-support`, `affiliates`, `rewards`, `vouchers`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when setup or publishing changes are involved
6. sibling contexts when an optional fulfilment rail is involved

## Guardrails
- `ReferralProgram` is the owner boundary. Always resolve invites and rewards through their program with `ReferralProgramGuard`.
- Reward specs are validated once and copied onto immutable reward rows when the invite qualifies. Fulfil only the frozen row through its configured adapter.
- This package owns MGM attribution and reward intent. Affiliates owns commercial attribution and cash accounting; Rewards owns points ledgers; Vouchers owns voucher lifecycle.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: an existing member invites a known contact and a host later reports a meaningful qualifying event.
- Skip when: commercial partner attribution or commission accounting belongs in `affiliates`; a standalone points entitlement belongs in `rewards`; voucher issuance or redemption rules belong in `vouchers`.
- Owner/security: Owner-scoped through `ReferralProgram`; invite and reward lookups must be revalidated for the current owner.

## Key surfaces
- Models: `ReferralProgram`, `ReferralInvite`, `ReferralReward`
- Actions: `CreateReferralProgram`, `CreateReferralInvite`, `AcceptReferralInvite`, `QualifyReferral`, `FulfillReferralReward`, `ReverseReferralReward`, `RevokeReferralInvite`
- Fulfilment: manual, vouchers, affiliates cash, rewards points; unavailable drivers fail closed.
- Config `referrals.php`: `database` (`json_column_type`, `tables.programs`, `tables.invites`, `tables.rewards`), `defaults` (`currency`, `invite_expiry_days`, `token_length`), `owner`, `fulfillment` (`default_driver`, `drivers.*`)

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: optional rails are documented in `04-usage` and their owning packages' docs.
