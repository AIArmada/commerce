---
title: Filament Contacting Troubleshooting
---

# Troubleshooting

## Resources Not Visible

Resources are **disabled by default**. Check your config:

```php
// config/filament-contacting.php
'resources' => [
    'contact_methods' => ['enabled' => true],
    'social_profiles' => ['enabled' => true],
    'contact_snapshots' => ['enabled' => true],
],
```

Also ensure `standalone_resources` is `true`:

```php
'features' => [
    'standalone_resources' => true,
],
```

## Plugin Not Registered

Add the plugin to your Panel provider:

```php
use AiArmada\FilamentContacting\FilamentContactingPlugin;

$panel->plugins([
    FilamentContactingPlugin::make(),
]);
```

## Standalone Resources Disabled by Default

Standalone resources are off by default to prevent accidental cross-owner access. Enable them only if your app has safe owner/contactable resolution.

## Relation Manager Relationship Missing

If the relation manager shows "Relationship [contactMethods] not found", ensure the parent model uses:

```php
use AiArmada\Contacting\Concerns\HasContactMethods;
use AiArmada\Contacting\Concerns\HasSocialProfiles;
```

## Create Button Hidden

The create button is hidden when the resource is read-only:

```php
'resources' => [
    'contact_methods' => [
        'enabled' => true,
        'read_only' => false,  // Set to false to show create/edit buttons
    ],
],
```

Snapshots are always read-only (`read_only: true`).

## Cross-Tenant Records Visible

If your app uses owner scoping from `commerce-support` and standalone resources are enabled, ensure the resource query applies owner scoping. The resource uses `parent::getEloquentQuery()` which respects the core model's global scope.

## Import Failing

Imports are disabled by default (`imports: false`). To enable, set to `true` and ensure your app provides an owner-safe contactable/socialable resolver.

## Filament Method Signature Mismatch

If you see method signature errors, check that your installed Filament version matches `^5.6.7`. The components in this package use Filament v5 APIs.