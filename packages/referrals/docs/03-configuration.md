---
title: Referrals Configuration
---

# Referrals Configuration

The complete file is `config/referrals.php`. Every setting below is read by the package.

## Database

| Key | Default | Purpose |
| --- | --- | --- |
| `referrals.database.json_column_type` | `jsonb` (or `REFERRALS_JSON_COLUMN_TYPE`) | JSON database type used by migrations through `commerce_json_column_type('referrals')`. |
| `referrals.database.tables.programs` | `referral_programs` | Configurable program table. |
| `referrals.database.tables.invites` | `referral_invites` | Configurable invite table. |
| `referrals.database.tables.rewards` | `referral_rewards` | Configurable reward-intent table. |

## Defaults

| Key | Default | Purpose |
| --- | --- | --- |
| `referrals.defaults.currency` | `MYR` | Currency used when a program does not specify one. |
| `referrals.defaults.invite_expiry_days` | `30` | Invite lifetime when a program has no override. |
| `referrals.defaults.token_length` | `32` | Random plaintext token length, accepted from 16 through 128. Only its SHA-256 hash is stored. |

## Owner boundary

| Key | Default | Purpose |
| --- | --- | --- |
| `referrals.owner.enabled` | `true` | Enables `commerce-support` owner scoping on programs and requires an owner or explicit global context for actions. |
| `referrals.owner.include_global` | `false` | Allows global programs to appear in a resolved owner's scope. Global writes still require explicit global context. |
| `referrals.owner.auto_assign_on_create` | `true` | Lets the shared owner trait assign the current owner when a program is created. |
| `referrals.owner.owner_type_column` | `owner_type` | Morph type column used by the owner trait and program creation. |
| `referrals.owner.owner_id_column` | `owner_id` | Morph ID column used by the owner trait and program creation. |

## Fulfilment

| Key | Default | Purpose |
| --- | --- | --- |
| `referrals.fulfillment.default_driver` | `manual` | Fallback driver if a reward row has no driver. Unknown, disabled, or unavailable drivers fail closed. |
| `referrals.fulfillment.drivers.manual.enabled` | `true` | Registers the manual adapter. |
| `referrals.fulfillment.drivers.manual.reference_metadata_key` | `external_reference` | Reward metadata key containing the host's fulfilment reference. |
| `referrals.fulfillment.drivers.voucher.enabled` | `true` | Enables voucher adapter registration when `aiarmada/vouchers` is available. |
| `referrals.fulfillment.drivers.affiliate_cash.enabled` | `true` | Enables affiliate cash adapter registration when `aiarmada/affiliates` is available. |
| `referrals.fulfillment.drivers.reward_points.enabled` | `true` | Enables rewards points adapter registration when `aiarmada/rewards` is available. |

There are no feature flags. Each setting controls a value used by the provider, model, migration, action, shared owner trait, or fulfilment router/adapter.
