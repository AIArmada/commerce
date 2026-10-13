---
title: Filament Referrals Overview
---

# Filament Referrals Overview

aiarmada/filament-referrals is the Filament v5 admin adapter for [aiarmada/referrals](../../referrals/docs/01-overview.md). It provides program, invite, and reward resources plus a referral statistics widget.

## What this package owns

- Filament resources, tables, forms, infolists, relation managers, navigation, and statistics.
- Operator actions that call the referrals package's guarded Actions.
- A shared query scope that limits invite and reward rows through owner-visible referral programs.

## What this package does not own

- Program persistence rules, invite tokens, contact privacy, qualification, frozen reward specifications, fulfilment, or reversal.
- Identity, contact capture, referral delivery, or deciding whether a member has qualified.
- Voucher, affiliate cash, or reward points behavior.

The core referrals package owns those domain rules. This adapter calls CreateReferralProgram, RevokeReferralInvite, FulfillReferralReward, and ReverseReferralReward. Edits and program status actions resolve the program through ReferralProgramGuard because the core package currently has no update or program status-transition Action.

See [installation](02-installation.md), [configuration](03-configuration.md), [usage](04-usage.md), and [troubleshooting](99-troubleshooting.md).
