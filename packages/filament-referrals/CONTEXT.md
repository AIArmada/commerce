---
title: Filament Referrals Context
package: filament-referrals
status: current
surface: adapter
family: growth-and-incentives
keywords:
  - filament
  - referral-admin
  - referral-invites
  - referral-rewards
---

# Filament Referrals Context

## Snapshot
- Composer: aiarmada/filament-referrals
- Role: Filament v5 adapter for aiarmada/referrals programs, invites, rewards, and owner-scoped statistics.
- Triggers: referral admin, referral invite table, referral reward fulfilment, Filament referrals
- Search first: src/Resources, src/Support, src/Widgets, config, docs
- Related: referrals, commerce-support

## Read next
1. docs/01-overview.md
2. docs/03-configuration.md
3. docs/04-usage.md
4. docs/99-troubleshooting.md
5. docs/02-installation.md when setup or publishing changes are involved
6. ../referrals/CONTEXT.md for domain rules and core Actions

## Guardrails
- Adapter only: core referrals owns persistence, qualification, and reward transitions.
- Programs use the core ReferralProgram OwnerScope. Invite and reward queries, relation managers, filters, and widget counts constrain through ReferralFilamentScope.
- Re-resolve action records through ReferralProgramGuard and invoke the core invite/reward Actions. Never render invite token hashes or decrypted invitee contacts.
- Program creation calls CreateReferralProgram and edits call UpdateReferralProgram, which re-resolves the program through the core guard under a row lock; program status moves only through the matrix-checked table actions.
- Navigation reads filament-referrals.navigation.* from config; never use static $navigationGroup.
- Update the canonical docs/*.md when public behavior or config changes.

## Decide fast
- Use when: operators need to inspect owner-scoped referral programs, invites, and rewards in Filament.
- Skip when: host identity, invite delivery, qualification policy, reward intent, or fulfilment behavior needs to change; those belong in referrals or its optional rails.
- Owner/security: program scope defines visibility for its invites and rewards; every mutation revalidates the owner on the server.

## Key surfaces
- Resources: ReferralProgramResource, ReferralInviteResource, ReferralRewardResource
- Support: ReferralFilamentScope, ReferralAdminPresenter, ReferralProgramFormData
- Widget: ReferralStatsWidget
- Actions: core CreateReferralProgram, UpdateReferralProgram, RevokeReferralInvite, FulfillReferralReward, and ReverseReferralReward
- Config: filament-referrals.php navigation group and sort positions
