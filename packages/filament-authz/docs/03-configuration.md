---
title: Configuration
---

# Configuration

Filament Authz can be configured globally via the config file or per-panel via the fluent Plugin API.

## Fluent Plugin API

The recommended way to configure the package is within your Panel provider.

```php
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;

$panel->plugins([
    FilamentAuthzPlugin::make()
        ->roleResource() // Register the Role resource
        ->permissionResource(false) // Disable Permission resource
        ->navigationGroup('System') // Set navigation group
        ->navigationIcon('heroicon-o-lock-closed') // Set custom icon
        ->navigationSort(5) // Sort order in sidebar
        ->gridColumns(3) // Role form tab grid columns
        ->checkboxColumns(5) // Checksbox grid columns per section
        ->excludeResources([UserResource::class]) // Exclude from discovery
        ->permissionCase('snake') // Use snake_case for keys
        ->permissionSeparator(':') // Use : as separator
        ->scopedToTenant() // Enable tenant scoping
        ->tenantOwnershipRelationship('team') // Define tenant relationship
]);
```

## Config File Options

The `config/filament-authz.php` file contains default settings used across all panels.

### Models
Defines the models used for Roles and Permissions.
```php
'models' => [
    'role' => AIArmada\FilamentAuthz\Models\Role::class,
    'permission' => AIArmada\FilamentAuthz\Models\Permission::class,
],
```

### Discovery
Global exclusions for resources, pages, and widgets.
```php
'resources' => [
    'exclude' => [],
],
'pages' => [
    'exclude' => [],
],
'widgets' => [
    'exclude' => [],
],
```

### Permissions Format
How keys are generated.
```php
'permissions' => [
    'separator' => '.',
    'case' => 'kebab', // Options: snake, kebab, camel, pascal, upper_snake, lower
],
```

### UI Features
Toggles for the Role resource UI.
```php
'role_resource' => [
    'tabs' => [
        'resources' => true,
        'pages' => true,
        'widgets' => true,
        'custom_permissions' => true,
    ],
    'grid_columns' => 2,
    'checkbox_columns' => 3,
],
```

### Multitenancy
Settings for owner-scoping.
```php
'scoped_to_tenant' => false,
'tenant_ownership_relationship' => 'owner',
```
