---
title: Rewards configuration
---

# Rewards configuration

All keys live in `config/rewards.php`. Every key is read by package code; there are no decorative options.

## Database

```php
'database' => [
    'json_column_type' => env('REWARDS_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'programs' => env('REWARDS_TABLE_PROGRAMS', 'reward_programs'),
        // ... one entry per table (30 tables)
    ],
],
```

Models resolve their table from `tables.*`; migrations create the configured names. `json_column_type` switches JSON columns between `json` and `jsonb`. Table defaults share a `$tablePrefix` local (`REWARDS_TABLE_PREFIX`, default `reward_`); it is not a config key.

## Ownership

```php
'owner' => [
    'enabled' => (bool) env('REWARDS_OWNER_ENABLED', true),
    'include_global' => (bool) env('REWARDS_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => (bool) env('REWARDS_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

The program is the tenant boundary. Global (owner-less) programs are platform programs visible wherever global inclusion is enabled.

Ownership is enforced on host request paths. System paths (queued jobs,
correction sweeps, artisan commands) run without an ambient owner by
design and enter each program's owner themselves via
`RewardProgramGuard::withProgramOwner()`; a `null` owner tuple is treated
as the explicit-global context.

That fan-out applies only when **no** owner is resolved. When an owner is
already resolved — including an explicit-global context — evaluation stays
scoped to it, so a synchronous in-tenant dispatch (the referrals points
rail) never reaches another tenant's programs.

## Registries

```php
'currencies' => ['MYR', 'USD', 'SGD', 'IDR', 'THB', 'GBP', 'EUR', 'AUD'],

'limits' => [
    'max_points' => 1000000,
    'max_minor_units' => 999999999999,
],

'outcomes' => [],              // host source-outcome registry (fail closed)
'moderated_outcomes' => [],    // outcomes moderation may vouch for
'recipient_types' => [],       // allowlisted recipient/member morph types (fail closed)

'cap_scopes' => [
    'recipient_source_component',
    'recipient_program_day',
    'recipient_program_month',
    'program_day',
    'program_total',
    'family_source_component',
],

'payouts' => [
    'methods' => ['manual'],   // methods the default catalog serves
],
```

> [!warning]
> `outcomes` and `recipient_types` start empty on purpose: evaluation refuses every outcome and recipient type until the host registers its own. There is no silent default that pays the wrong people.

Next: [usage](04-usage.md). See also [troubleshooting](99-troubleshooting.md).
