---
title: Configuration
---

# Configuration

The package configuration lives in `config/filament-persons.php`:

```php
return [
    'navigation' => [
        'group' => 'People',
    ],
    'resources' => [
        'enabled' => [
            'person' => true,
            'title' => true,
            'title_issuer' => true,
            'credential_definition' => true,
        ],
        'navigation_sort' => [
            'person' => 1,
            'title' => 10,
            'title_issuer' => 11,
            'credential_definition' => 12,
        ],
    ],
];
```

Set an entry in `resources.enabled` to `false` to keep that resource out of
the panel. Navigation groups and sort positions are read at runtime by each
resource, so panel-specific navigation overrides remain possible.

Publish the configuration when a local copy is needed:

```bash
php artisan vendor:publish --tag=filament-persons-config
```

Domain model configuration belongs to [aiarmada/persons](../../persons/docs/03-configuration.md).
