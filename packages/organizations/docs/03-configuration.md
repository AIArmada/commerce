---
title: Organizations Configuration
---

## Configuration

`config/organizations.php` controls the organization and membership table
names, the current-organization resolver, and whether middleware requires a
resolved context by default.

```php
'middleware' => [
    'require_context' => false,
],
```

Route parameters can require context explicitly with
`current.organization:true`.

## Authorization

Bind `OrganizationAuthorization`,
`OrganizationVisibilityTransitionAuthorizer`, and
`OrganizationLifecycleHook` to application implementations when the default
membership-role policy is not sufficient.
