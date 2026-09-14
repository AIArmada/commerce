---
title: Troubleshooting
---

## Overrides not taking effect

1. Verify `commerce-support.filament.navigation.enabled` is `true`
2. Verify `filament-commerce-support.navigation.enabled` is `true`
3. Confirm the component class FQCN is correct (case-sensitive)
4. Check the `settings` table exists and has a `commerce-navigation` row

## Settings page not appearing

1. Confirm the plugin is registered on the panel
2. Verify `filament-commerce-support.navigation.enabled` is `true`
3. Check the panel user is authorized for the configured `navigation.permission` Gate ability

## "Settings storage is unavailable" warning

The manager shows file-config defaults when the `settings` table (or its
`commerce-navigation` rows) cannot be read. Run the settings migrations;
saving recreates missing rows automatically, but cannot work while the
table itself is missing.

## Contributor interface

If you maintain a `filament-*` package and want to participate in the navigation system, implement `CommerceNavigationContributorInterface` and tag your contributor in the service provider:

```php
$this->app->tag(YourContributor::class, 'commerce.navigation.contributors');
```

Your contributor's `contribute(Panel $panel)` method will be called during panel registration.
