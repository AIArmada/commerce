---
title: Troubleshooting
---

# Troubleshooting

## Cache Issues

If permissions are not reflecting correctly, clear the Spatie permission cache:

```bash
php artisan permission:cache-reset
```

If you're using the Authz discovery cache, clear it programmatically:

```php
use AIArmada\FilamentAuthz\Facades\Authz;

Authz::clearCache();
```

## Entity Not Showing in Role Management

If a Resource, Page, or Widget isn't appearing in the Role management UI:

1. **Check panel registration** — Ensure it's registered in the current Filament panel
2. **Check exclusions** — Review `resources.exclude`, `pages.exclude`, or `widgets.exclude` in config
3. **Check plugin exclusions** — Review `->excludeResources()`, `->excludePages()`, `->excludeWidgets()` in plugin config
4. **Verify discovery** — Run `php artisan authz:discover --panel=admin` to see what's being discovered

## Super Admin Not Bypassing Permissions

1. **Verify role name** — Check that the role name matches `config('filament-authz.super_admin_role')`:
   ```php
   // Default is 'super_admin'
   config('filament-authz.super_admin_role');
   ```

2. **Verify role assignment** — Ensure the user actually has the role:
   ```php
   $user->hasRole('super_admin'); // Should return true
   ```

3. **Check guard** — Ensure the role was created with the correct guard:
   ```php
   $user->hasRole('super_admin', 'web');
   ```

## Trait Method Conflicts

If you have custom `canAccess()` logic in Pages or `canView()` in Widgets, you have two options:

### Option 1: Call parent method
```php
public static function canAccess(): bool
{
    // Your custom logic first
    if (! static::customCheck()) {
        return false;
    }
    
    // Then delegate to trait
    return parent::canAccess();
}
```

### Option 2: Integrate permission check
```php
public static function canAccess(): bool
{
    $permission = static::getAuthzPermission();
    
    return auth()->user()?->can($permission) && static::customCheck();
}
```

## Permissions Not Being Created

If `authz:discover --create` isn't creating permissions:

1. **Check guards** — Ensure guards in config exist in `config/auth.php`:
   ```bash
   php artisan tinker
   >>> config('auth.guards')
   ```

2. **Run migrations** — Ensure Spatie Permission migrations have run:
   ```bash
   php artisan migrate:status | grep permission
   ```

## Multi-Panel Issues

When using multiple panels with different configurations:

1. **Each panel needs its own plugin instance**:
   ```php
   // AdminPanelProvider
   FilamentAuthzPlugin::make()->navigationGroup('Admin Security')
   
   // CustomerPanelProvider  
   FilamentAuthzPlugin::make()->roleResource(false)->permissionResource(false)
   ```

2. **Discovery is panel-specific** — Permissions are discovered per-panel, so run discover for each:
   ```bash
   php artisan authz:discover --panel=admin --create
   php artisan authz:discover --panel=customer --create
   ```
