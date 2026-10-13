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

## Owners

```php
'owners' => [
    'types' => [
        'organization' => ['model' => App\Models\Organization::class, 'label' => 'Organization', 'name_column' => 'name'],
    ],
],
```

Owner types offered by the program create form. Platform (global) programs need no entry and are always available.

Next: [usage](04-usage.md). See also [troubleshooting](99-troubleshooting.md).
