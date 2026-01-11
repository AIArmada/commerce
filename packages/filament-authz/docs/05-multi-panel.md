---
title: Multi-Panel Support
---

# Multi-Panel Support

Filament Authz is built to support applications with multiple Filament panels effortlessly.

## Per-Panel Configuration

You can register the plugin in different panels with different settings.

### Admin Panel
```php
// AdminPanelProvider.php
$panel->plugins([
    FilamentAuthzPlugin::make()
        ->navigationGroup('Security')
        ->excludeResources([UserResource::class])
]);
```

### Customer Panel
```php
// CustomerPanelProvider.php
$panel->plugins([
    FilamentAuthzPlugin::make()
        ->roleResource(false) // Don't allow customers to edit roles
        ->permissionResource(false)
        ->scopedToTenant() // Customers see only their roles
]);
```

## Discovery Scope
When a user visits a panel, the `EntityDiscoveryService` only identifies resources, pages, and widgets registered to that specific panel. This ensures that permissions are clean and relevant to the context.

## Role Resource in Multi-Panel
The Role resource form uses the current panel to discover what should be displayed in the tabs. If you have different resources in different panels, the Role management UI will reflect that.

### Tenant Scoping
If your panel uses tenant-scoping (e.g., via `scopedToTenant()`), the Role resource will automatically apply a global scope to ensure roles are only visible to the correct tenant.
