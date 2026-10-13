---
title: Filament Referrals Configuration
---

# Filament Referrals Configuration

All adapter settings are in config/filament-referrals.php.

## Navigation

| Key | Default | Purpose |
| --- | --- | --- |
| filament-referrals.navigation.group | Referrals | Navigation group returned by all three resources. |
| filament-referrals.navigation.sort.programs | 1 | Referral program resource sort order. |
| filament-referrals.navigation.sort.invites | 2 | Referral invite resource sort order. |
| filament-referrals.navigation.sort.rewards | 3 | Referral reward resource sort order. |

The CommerceNavigation engine can override these values at runtime. Owner configuration, default currency, and fulfilment drivers belong to the core [referrals config](../../referrals/docs/03-configuration.md).
