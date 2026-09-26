---
title: Configuration
---

## Configuration file

The `config/filament-engagement.php` file controls plugin behavior.

### Navigation

```php
'navigation' => [
    'group' => 'Engagement',
],
```

Customize the navigation group label for all engagement resources.

### Resource toggles

```php
'resources' => [
    'enabled' => [
        'follow' => true,
        'bookmark' => true,
        'collection' => true,
        'response' => true,
        'reaction' => true,
        'subscription' => true,
        'reminder' => true,
    ],
],
```

Each resource can be individually disabled by setting its key to `false`. The resource will not be registered in the Filament panel or appear in navigation.

### Resource sort order

```php
'resources' => [
    'navigation_sort' => [
        'follow' => 1,
        'bookmark' => 2,
        'collection' => 3,
        'response' => 4,
        'reaction' => 5,
        'subscription' => 6,
        'reminder' => 7,
    ],
],
```

Lower values sort first inside the `Engagement` navigation group.
