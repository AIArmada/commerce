---
title: Configuration
---

# Configuration

All settings live in `config/filament-links.php`.

## Navigation

| Key | Default | Description |
|---|---|---|
| `navigation.group` | `Links` | Navigation group for link resources. |

## Features

| Key | Default | Description |
|---|---|---|
| `features.links` | `true` | Register the link resource. |

## Access and managed subjects

| Key | Default | Description |
|---|---|---|
| `staff_roles` | `[]` | When configured, only users with one of these roles can access the resource. An empty list leaves authorization to the host panel and policies. |
| `protected_subject_types` | `null` | Subject model classes whose tracked links keep protected fields read-only in Filament and cannot be deleted there. When `null`, `AffiliateLink` is protected automatically if the affiliates package is installed. An explicit array overrides the default; `[]` disables automatic protection. Other link controls, including deactivation, remain available. |

```php
'staff_roles' => ['super_admin', 'admin'],
'protected_subject_types' => [AIArmada\Affiliates\Models\AffiliateLink::class],
```

## Resources

| Key | Default | Description |
|---|---|---|
| `resources.navigation_sort.links` | `10` | Navigation sort order for the link resource. |

## Read next

- [Usage](04-usage.md)
