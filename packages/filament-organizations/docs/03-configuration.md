---
title: Filament Organizations Configuration
---

## Navigation

Configure the resource navigation through the nested package config:

```php
'navigation' => [
    'group' => 'Organizations',
    'sort' => 10,
],
```

The resource reads these values through `getNavigationGroup()` and
`getNavigationSort()` so application navigation overrides remain possible.
