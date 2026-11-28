---
title: Configuration
---

# Configuration

The package configuration lives in `config/filament-persons.php`:

```php
return [
    'navigation' => [
        'group' => 'People',
        'sort' => 30,
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
        'person_relation_managers' => [],
        'institution' => [
            'label_column' => 'name',
            'search_columns' => null,
        ],
    ],
];
```

Set an entry in `resources.enabled` to `false` to keep that resource out of
the panel. `resources.person_relation_managers` accepts additional `RelationManager`
class names appended to `PersonResource::getRelations()`. `navigation.sort` registers the group order with the Commerce
navigation builder; `resources.navigation_sort.*` controls item order. All
values are read at runtime, so panel-specific navigation overrides remain
possible.

`resources.institution.label_column` selects the institution attribute shown
in the affiliation picker. It defaults to `name`. `resources.institution.search_columns`
lists the columns searched by the picker and defaults to the label column when
omitted or empty. Both values must be plain column identifiers; qualified names
and expressions are rejected. Point them at the columns supplied by your
configured `persons.models.institution` model when it does not use `name`.

Publish the configuration when a local copy is needed:

```bash
php artisan vendor:publish --tag=filament-persons-config
```

Domain model configuration belongs to [aiarmada/persons](../../persons/docs/03-configuration.md).
