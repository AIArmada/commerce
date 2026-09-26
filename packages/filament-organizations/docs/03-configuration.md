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

## Resources

```php
'resources' => [
    'enabled' => true,
],
```

Set `resources.enabled` to `false` to keep `OrganizationResource` out of every
panel. The plugin reads this flag before registering the resource, so no
resource-scoped navigation flag is needed.

## Rate limits

Any authenticated user may create organizations, so creations are throttled
per user:

```php
'rate_limits' => [
    'create_per_hour' => 10,
],
```
