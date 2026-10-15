---
title: Filament rewards configuration
---

# Filament rewards configuration

All keys live in `config/filament-rewards.php`.

## Navigation

```php
'navigation' => [
    'group' => 'Rewards',
    'sort' => [
        'programs' => 1,
        'awards' => 2,
        'appeals' => 3,
        'funding' => 10,
        'payees' => 11,
        'payouts' => 12,
        'benefit_items' => 13,
        'benefit_grants' => 14,
        'recoveries' => 15,
        'fraud' => 16,
    ],
],
```

Every resource reads its group and sort order from these keys. The `CommerceNavigation` engine can override any item at runtime.

## Staff

```php
'staff_roles' => ['super_admin', 'admin'],
```

Roles consulted when the authenticated user carries role methods (for example Spatie permissions). Panel users without role support count as staff.

### Staff boundary and Gate abilities

The staff role is a hard boundary, not just navigation:

- **Payees** (`RewardPayeeResource::canViewAny()`) and **program creation** (`RewardProgramResource::canCreate()`) refuse non-staff users with `403`. The create page repeats the check inside `handleRecordCreation()`, so a crafted Livewire call cannot bypass the resource gate.
- Every award, appeal, and fraud decision **re-checks the staff role and its Gate ability inside the action handler**. `visible()` only decides whether the button renders; the handler is the authorization boundary.

Hosts must therefore define the abilities these decisions consult — or a `Gate::before` that grants them:

| Ability | Consulted by |
| --- | --- |
| `approve` | Award **Approve** |
| `review` | Award **Reject** |
| `reverse` | Award **Reverse** |
| `dismissFraud` | Award **Dismiss fraud** |
| `decide` | Appeal **Decide** |
| `reward-fraud-steward` | Fraud stewardship page access and fraud **Dismiss** |

`RewardAdminPresenter::canDecideAward($record, $ability)` runs `Gate::forUser($user)->allows($ability, $record)`, so the record is passed as the Gate argument — define policies or abilities against `AIArmada\Rewards\Models\RewardAward` / `RewardAppeal` accordingly. A host that defines none of these abilities denies every decision (fail-closed). Role-less hosts (users without role methods) still count as staff for the role check, but the Gate abilities are always required.

## Owners

```php
'owners' => [
    'types' => [
        'organization' => ['model' => App\Models\Organization::class, 'label' => 'Organization', 'name_column' => 'name'],
    ],
],
```

Owner types offered by the program create form. Platform (global) programs need no entry and are always available. On submit the handler resolves the alias to the configured model, loads the record with `whereKey(...)`, and passes the **model instance** to `CreateRewardProgramAction`, so `owner_type` stores the model's morph class rather than the config alias. An alias that is not configured, or a record that cannot be found, fails validation on `owner_record_id` and writes nothing.

Next: [usage](04-usage.md). See also [troubleshooting](99-troubleshooting.md).
